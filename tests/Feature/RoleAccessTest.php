<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Department;
use App\Models\Faculty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $student;
    private User $teacher;
    private User $finance;
    private User $registrar;
    private \App\Models\Student $studentProfile;

    private string $adminToken;
    private string $studentToken;
    private string $teacherToken;
    private string $financeToken;
    private string $registrarToken;

    protected function setUp(): void
    {
        parent::setUp();

        $faculty = Faculty::factory()->create();
        $dept = Department::factory()->create(['faculty_id' => $faculty->id]);

        // Admin
        $this->admin = User::factory()->create(['username' => 'admin_t', 'role' => 'admin', 'is_active' => true]);
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;

        // Student (with student profile required by dashboard)
        $this->student = User::factory()->create(['username' => 'student_t', 'role' => 'student', 'is_active' => true]);
        $this->studentProfile = Student::create([
            'user_id'         => $this->student->id,
            'department_id'   => $dept->id,
            'student_id'      => 'STU-TEST-001',
            'level'           => 'L1',
            'enrollment_date' => now()->toDateString(),
            'status'          => 'active',
        ]);
        $this->studentToken = $this->student->createToken('test')->plainTextToken;

        // Teacher (with teacher profile required by dashboard)
        $this->teacher = User::factory()->create(['username' => 'teacher_t', 'role' => 'teacher', 'is_active' => true]);
        Teacher::create([
            'user_id'       => $this->teacher->id,
            'department_id' => $dept->id,
            'employee_id'   => 'TCH-TEST-001',
            'qualification' => 'PhD',
            'hire_date'     => now()->toDateString(),
            'status'        => 'active',
        ]);
        $this->teacherToken = $this->teacher->createToken('test')->plainTextToken;

        // Finance
        $this->finance = User::factory()->create(['username' => 'finance_t', 'role' => 'finance', 'is_active' => true]);
        $this->financeToken = $this->finance->createToken('test')->plainTextToken;

        // Registrar
        $this->registrar = User::factory()->create(['username' => 'registrar_t', 'role' => 'registrar', 'is_active' => true]);
        $this->registrarToken = $this->registrar->createToken('test')->plainTextToken;
    }

    // ============================
    // TEST UNAUTHENTICATED ACCESS
    // ============================

    public function test_unauthenticated_access_returns_401()
    {
        $routes = [
            ['GET',  '/api/me'],
            ['GET',  '/api/dashboard/admin'],
            ['GET',  '/api/students'],
            ['GET',  '/api/fee-types'],
            ['POST', '/api/logout'],
        ];

        foreach ($routes as [$method, $route]) {
            $resp = $this->json($method, $route);
            $this->assertEquals(401, $resp->status(),
                "$method $route should be 401 for unauthenticated, got {$resp->status()}");
        }
    }

    // ============================
    // DASHBOARD ACCESS PER ROLE
    // ============================

    public function test_admin_can_access_admin_dashboard()
    {
        $this->withToken($this->adminToken)->getJson('/api/dashboard/admin')->assertStatus(200);
    }

    public function test_non_admin_cannot_access_admin_dashboard()
    {
        foreach ([$this->studentToken, $this->teacherToken, $this->financeToken, $this->registrarToken] as $token) {
            $this->withToken($token)->getJson('/api/dashboard/admin')->assertStatus(403);
        }
    }

    public function test_student_can_access_student_dashboard()
    {
        $this->withToken($this->studentToken)->getJson('/api/dashboard/student')->assertStatus(200);
    }

    public function test_non_student_cannot_access_student_dashboard()
    {
        foreach ([$this->adminToken, $this->teacherToken, $this->financeToken] as $token) {
            $this->withToken($token)->getJson('/api/dashboard/student')->assertStatus(403);
        }
    }

    public function test_teacher_can_access_teacher_dashboard()
    {
        $this->withToken($this->teacherToken)->getJson('/api/dashboard/teacher')->assertStatus(200);
    }

    public function test_non_teacher_cannot_access_teacher_dashboard()
    {
        foreach ([$this->adminToken, $this->studentToken, $this->financeToken] as $token) {
            $this->withToken($token)->getJson('/api/dashboard/teacher')->assertStatus(403);
        }
    }

    public function test_finance_can_access_finance_dashboard()
    {
        $this->withToken($this->financeToken)->getJson('/api/dashboard/finance')->assertStatus(200);
    }

    public function test_non_finance_cannot_access_finance_dashboard()
    {
        foreach ([$this->adminToken, $this->studentToken, $this->teacherToken] as $token) {
            $this->withToken($token)->getJson('/api/dashboard/finance')->assertStatus(403);
        }
    }

    public function test_registrar_can_access_registrar_dashboard()
    {
        $this->withToken($this->registrarToken)->getJson('/api/dashboard/registrar')->assertStatus(200);
    }

    public function test_non_registrar_cannot_access_registrar_dashboard()
    {
        foreach ([$this->adminToken, $this->studentToken, $this->teacherToken] as $token) {
            $this->withToken($token)->getJson('/api/dashboard/registrar')->assertStatus(403);
        }
    }

    // ============================
    // STUDENT CRUD (admin/registrar)
    // ============================

    public function test_admin_and_registrar_can_list_students()
    {
        $this->withToken($this->adminToken)->getJson('/api/students')->assertStatus(200);
        $this->withToken($this->registrarToken)->getJson('/api/students')->assertStatus(200);
    }

    public function test_student_teacher_finance_cannot_list_students()
    {
        foreach ([$this->studentToken, $this->teacherToken, $this->financeToken] as $token) {
            $this->withToken($token)->getJson('/api/students')->assertStatus(403);
        }
    }

    // ============================
    // FEE TYPES (admin/finance)
    // ============================

    public function test_admin_and_finance_can_access_fee_types()
    {
        $this->withToken($this->adminToken)->getJson('/api/fee-types')->assertStatus(200);
        $this->withToken($this->financeToken)->getJson('/api/fee-types')->assertStatus(200);
    }

    public function test_student_teacher_cannot_access_fee_types()
    {
        foreach ([$this->studentToken, $this->teacherToken] as $token) {
            $this->withToken($token)->getJson('/api/fee-types')->assertStatus(403);
        }
    }

    // ============================
    // GRADES WRITE (admin/teacher)
    // ============================

    public function test_admin_and_teacher_can_write_grades()
    {
        // Empty body returns 422 (validation error), not 403
        $this->withToken($this->adminToken)
            ->postJson('/api/grades', [])->assertStatus(422);
        $this->withToken($this->teacherToken)
            ->postJson('/api/grades', [])->assertStatus(422);
    }

    public function test_student_finance_cannot_write_grades()
    {
        foreach ([$this->studentToken, $this->financeToken] as $token) {
            $this->withToken($token)->postJson('/api/grades', [])->assertStatus(403);
        }
    }

    // ============================
    // COURSES (read-only for auth)
    // ============================

    public function test_authenticated_users_can_read_courses()
    {
        // Admin, teacher, student can all read courses
        foreach ([$this->adminToken, $this->teacherToken, $this->studentToken] as $token) {
            $this->withToken($token)->getJson('/api/courses')->assertStatus(200);
        }
    }

    public function test_admin_can_write_courses()
    {
        // Admin gets 422 (validation error) — meaning access is granted
        $this->withToken($this->adminToken)
            ->postJson('/api/courses', [])->assertStatus(422);
    }

    public function test_teacher_cannot_write_courses()
    {
        $this->withToken($this->teacherToken)->postJson('/api/courses', [])->assertStatus(403);
    }

    public function test_student_cannot_write_courses()
    {
        $this->withToken($this->studentToken)->postJson('/api/courses', [])->assertStatus(403);
    }

    // ============================
    // ADMIN-ONLY ROUTES
    // ============================

    public function test_admin_can_access_activity_logs()
    {
        $this->withToken($this->adminToken)->getJson('/api/activity-logs')->assertStatus(200);
    }

    public function test_student_cannot_access_activity_logs()
    {
        $this->withToken($this->studentToken)->getJson('/api/activity-logs')->assertStatus(403);
    }

    public function test_teacher_cannot_access_activity_logs()
    {
        $this->withToken($this->teacherToken)->getJson('/api/activity-logs')->assertStatus(403);
    }

    public function test_finance_cannot_access_activity_logs()
    {
        $this->withToken($this->financeToken)->getJson('/api/activity-logs')->assertStatus(403);
    }

    public function test_admin_can_access_student_management_routes()
    {
        // Use real student ID so binding succeeds — admin gets 200
        $sid = $this->studentProfile->id;
        $this->withToken($this->adminToken)
            ->getJson("/api/student-management/{$sid}/profile")
            ->assertStatus(200);
    }

    public function test_student_cannot_access_student_management_routes()
    {
        $sid = $this->studentProfile->id;
        $this->withToken($this->studentToken)
            ->getJson("/api/student-management/{$sid}/profile")->assertStatus(403);
    }

    public function test_teacher_cannot_access_student_management_routes()
    {
        $sid = $this->studentProfile->id;
        $this->withToken($this->teacherToken)
            ->getJson("/api/student-management/{$sid}/profile")->assertStatus(403);
    }

    public function test_finance_cannot_access_student_management_routes()
    {
        $sid = $this->studentProfile->id;
        $this->withToken($this->financeToken)
            ->getJson("/api/student-management/{$sid}/profile")->assertStatus(403);
    }

    // ============================
    // REGISTRAR ROUTES
    // ============================

    public function test_registrar_and_admin_can_list_users()
    {
        $this->withToken($this->registrarToken)->getJson('/api/registrar/users')->assertStatus(200);
        $this->withToken($this->adminToken)->getJson('/api/registrar/users')->assertStatus(200);
    }

    public function test_non_registrar_non_admin_cannot_access_registrar_routes()
    {
        foreach ([$this->studentToken, $this->teacherToken, $this->financeToken] as $token) {
            $this->withToken($token)->getJson('/api/registrar/users')->assertStatus(403);
        }
    }
}
