<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Models\ActivityLog;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // ── Helpers ────────────────────────────────────────────────────────────────

    private function maskedEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email);
        $masked = substr($local, 0, 1) . str_repeat('*', max(1, strlen($local) - 2)) . substr($local, -1);
        return $masked . '@' . $domain;
    }

    private function loadUserRelations(User $user): void
    {
        if ($user->role === 'student') {
            $user->load(['student.department.faculty']);
        } elseif ($user->role === 'teacher') {
            $user->load(['teacher.department.faculty']);
        }
    }

    // ── Step 1 : validate credentials, send OTP ────────────────────────────────

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)
            ->orWhere('email', $request->username)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        if (!$user->is_active) {
            return $this->error('Votre compte a été désactivé. Contactez l\'administrateur.', 403);
        }

        if (empty($user->email)) {
            return $this->error('Aucun email associé à ce compte. Contactez l\'administrateur.', 422);
        }

        // Generate OTP and send by email
        $otp = OtpCode::generate($user, 'login');
        Mail::to($user->email)->send(new OtpMail($user, $otp->code, 'login'));

        return response()->json([
            'status'        => 'otp_required',
            'masked_email'  => $this->maskedEmail($user->email),
            'message'       => 'Un code de vérification a été envoyé à votre adresse email.',
        ]);
    }

    // ── Step 2 : verify OTP, issue token ──────────────────────────────────────

    public function verifyLoginOtp(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'code'     => 'required|string|size:6',
        ]);

        $user = User::where('username', $request->username)
            ->orWhere('email', $request->username)
            ->first();

        if (!$user) {
            return $this->error('Utilisateur introuvable.', 404);
        }

        $otp = OtpCode::where('user_id', $user->id)
            ->where('type', 'login')
            ->where('code', $request->code)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (!$otp) {
            return $this->error('Code incorrect.', 422);
        }

        if (!$otp->isValid()) {
            return $this->error('Code expiré. Veuillez vous reconnecter pour en recevoir un nouveau.', 422);
        }

        // Mark OTP as used
        $otp->update(['used_at' => now()]);

        // Issue token
        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('auth-token')->plainTextToken;
        ActivityLog::log('login', 'User logged in (OTP verified)', $user);

        $this->loadUserRelations($user);

        return $this->success([
            'user'  => $user,
            'token' => $token,
        ], 'Connexion réussie');
    }

    // ── Resend OTP ──────────────────────────────────────────────────────────────

    public function resendOtp(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'type'     => 'required|in:login,password_reset',
        ]);

        $user = User::where('username', $request->username)
            ->orWhere('email', $request->username)
            ->first();

        if (!$user || empty($user->email)) {
            return $this->error('Utilisateur introuvable.', 404);
        }

        $otp = OtpCode::generate($user, $request->type);
        Mail::to($user->email)->send(new OtpMail($user, $otp->code, $request->type));

        return response()->json([
            'message'      => 'Un nouveau code a été envoyé.',
            'masked_email' => $this->maskedEmail($user->email),
        ]);
    }

    // ── Forgot password : send OTP ─────────────────────────────────────────────

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        // Always return success to avoid email enumeration
        if (!$user || empty($user->email)) {
            return response()->json(['message' => 'Si cet email existe, un code vous a été envoyé.']);
        }

        $otp = OtpCode::generate($user, 'password_reset');
        Mail::to($user->email)->send(new OtpMail($user, $otp->code, 'password_reset'));

        return response()->json([
            'message'      => 'Un code de réinitialisation a été envoyé à votre adresse email.',
            'masked_email' => $this->maskedEmail($user->email),
        ]);
    }

    // ── Reset password : verify OTP + update password ─────────────────────────

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'                 => 'required|email',
            'code'                  => 'required|string|size:6',
            'password'              => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->error('Utilisateur introuvable.', 404);
        }

        $otp = OtpCode::where('user_id', $user->id)
            ->where('type', 'password_reset')
            ->where('code', $request->code)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (!$otp) {
            return $this->error('Code incorrect.', 422);
        }

        if (!$otp->isValid()) {
            return $this->error('Code expiré. Veuillez recommencer.', 422);
        }

        $otp->update(['used_at' => now()]);
        $user->update(['password' => Hash::make($request->password)]);

        ActivityLog::log('password_reset', 'User reset password via OTP', $user);

        return response()->json(['message' => 'Mot de passe mis à jour avec succès. Vous pouvez vous connecter.']);
    }

    // ── Existing methods ───────────────────────────────────────────────────────

    public function logout(Request $request)
    {
        ActivityLog::log('logout', 'User logged out');
        $request->user()->currentAccessToken()->delete();
        return $this->success(null, 'Logged out successfully');
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $this->loadUserRelations($user);
        return $this->success($user);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'first_name'    => 'sometimes|string|max:255',
            'last_name'     => 'sometimes|string|max:255',
            'phone'         => 'sometimes|nullable|string|max:20',
            'address'       => 'sometimes|nullable|string|max:255',
            'date_of_birth' => 'sometimes|nullable|date',
        ]);

        $user->update($request->only(['first_name', 'last_name', 'phone', 'address', 'date_of_birth']));
        ActivityLog::log('profile_update', 'User updated profile', $user);

        return $this->success($user, 'Profile updated successfully');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->error('Current password is incorrect', 400);
        }

        $user->update(['password' => Hash::make($request->password)]);
        ActivityLog::log('password_change', 'User changed password', $user);

        return $this->success(null, 'Password changed successfully');
    }
}
