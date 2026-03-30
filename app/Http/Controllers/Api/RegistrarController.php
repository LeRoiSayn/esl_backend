<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class RegistrarController extends Controller
{
    /**
     * List users by role (admin, finance, registrar).
     */
    public function listUsers(Request $request)
    {
        $this->authorizeRegistrar($request);

        $role = $request->query('role', 'admin');

        if (!in_array($role, ['admin', 'finance', 'registrar'])) {
            return $this->error('Invalid role', 422);
        }

        $users = User::where('role', $role)
            ->select('id', 'first_name', 'last_name', 'email', 'username', 'phone', 'date_of_birth', 'is_active', 'status', 'profile_image', 'employee_id', 'created_at')
            ->orderBy('first_name')
            ->get();

        return $this->success($users);
    }

    /**
     * Create a new admin, finance, or registrar user.
     */
    public function createUser(Request $request)
    {
        $this->authorizeRegistrar($request);

        $data = $request->validate([
            'first_name'    => 'required|string|max:255',
            'last_name'     => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'username'      => 'required|string|max:100|unique:users,username',
            'password'      => 'required|string|min:8',
            'phone'         => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'role'          => ['required', Rule::in(['admin', 'finance', 'registrar'])],
            'profile_image' => 'nullable|image|max:2048',
        ]);

        $profileImagePath = null;
        if ($request->hasFile('profile_image')) {
            $profileImagePath = $request->file('profile_image')->store('profile-images', 'public');
        }

        $employeeId = $this->generateEmployeeId($data['role']);

        $user = User::create([
            'first_name'    => $data['first_name'],
            'last_name'     => $data['last_name'],
            'email'         => $data['email'],
            'username'      => $data['username'],
            'password'      => Hash::make($data['password']),
            'phone'         => $data['phone'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'role'          => $data['role'],
            'profile_image' => $profileImagePath,
            'is_active'     => true,
            'employee_id'   => $employeeId,
        ]);

        ActivityLog::log('user_create', "Registrar created {$data['role']} account: {$user->username}", $request->user());

        return $this->success($user, ucfirst($data['role']) . ' created successfully', 201);
    }

    /**
     * Delete an admin/finance/registrar user.
     */
    public function deleteUser(Request $request, $id)
    {
        $this->authorizeRegistrar($request);

        $user = User::whereIn('role', ['admin', 'finance', 'registrar'])->findOrFail($id);

        // Prevent self-deletion
        if ($user->id === $request->user()->id) {
            return $this->error('You cannot delete your own account', 403);
        }

        if ($user->profile_image) {
            Storage::disk('public')->delete($user->profile_image);
        }

        ActivityLog::log('user_delete', "Registrar deleted user: {$user->username}", $request->user());
        $user->delete();

        return $this->success(null, 'User deleted successfully');
    }

    /**
     * Reset a user's password (any role). Only registrar can do this.
     */
    public function resetPassword(Request $request, $id)
    {
        $this->authorizeRegistrar($request);

        $data = $request->validate([
            'password' => 'required|string|min:8',
        ]);

        $user = User::findOrFail($id);

        // Prevent resetting own password via this route (use change-password instead)
        if ($user->id === $request->user()->id) {
            return $this->error('Use the change-password endpoint to change your own password', 403);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        ActivityLog::log('password_reset', "Registrar reset password for: {$user->username}", $request->user());

        return $this->success(null, 'Password reset successfully');
    }

    /**
     * Update a user's full profile (registrar only) — includes email, username, password, photo.
     */
    public function updateUserProfile(Request $request, $id)
    {
        $this->authorizeRegistrar($request);

        $user = User::findOrFail($id);

        $data = $request->validate([
            'first_name'    => 'sometimes|string|max:255',
            'last_name'     => 'sometimes|string|max:255',
            'email'         => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'username'      => ['sometimes', 'string', 'max:100', Rule::unique('users', 'username')->ignore($user->id)],
            'password'      => 'sometimes|nullable|string|min:8',
            'phone'         => 'sometimes|nullable|string|max:20',
            'address'       => 'sometimes|nullable|string|max:255',
            'date_of_birth' => 'sometimes|nullable|date',
            'status'        => 'sometimes|in:active,inactive,on_leave',
            'profile_image' => 'sometimes|nullable|image|max:2048',
        ]);

        // Handle password
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // Handle profile image upload
        if ($request->hasFile('profile_image')) {
            if ($user->profile_image) {
                Storage::disk('public')->delete($user->profile_image);
            }
            $data['profile_image'] = $request->file('profile_image')->store('profile-images', 'public');
        } else {
            unset($data['profile_image']);
        }

        $user->update($data);

        ActivityLog::log('profile_update', "Registrar updated profile for: {$user->username}", $request->user());

        return $this->success($user->fresh(), 'Profile updated successfully');
    }

    // ─── Private helpers ────────────────────────────────────────────────────

    private function generateEmployeeId(string $role): string
    {
        $prefixes = [
            'admin'     => 'ADM',
            'finance'   => 'FIN',
            'registrar' => 'REG',
        ];

        $prefix = $prefixes[$role] ?? 'EMP';

        $last = User::where('role', $role)
            ->whereNotNull('employee_id')
            ->orderByDesc('employee_id')
            ->value('employee_id');

        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        } else {
            $next = 1;
        }

        return $prefix . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    private function authorizeRegistrar(Request $request): void
    {
        if (!in_array($request->user()?->role, ['registrar', 'admin'])) {
            abort(403, 'Only registrars or admins can perform this action.');
        }
    }
}
