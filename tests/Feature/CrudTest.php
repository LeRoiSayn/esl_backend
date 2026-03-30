<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\Course;
use App\Models\FeeType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $finance;
    private User $registrar;
    private string $adminToken;
    private string $financeToken;
    private string $registrarToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'username' => 'admin_crud',
            'role'     => 'admin',
            'is_active'=> true,
        ]);
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;

        $this->finance = User::factory()->create([
            'username' => 'finance_crud',
            'role'     => 'finance',
            'is_active'=> true,
        ]);
        $this->financeToken = $this->finance->createToken('test')->plainTextToken;

        $this->registrar = User::factory()->create([
            'username' => 'registrar_crud',
            'role'     => 'registrar',
            'is_active'=> true,
        ]);
        $this->registrarToken = $this->registrar->createToken('test')->plainTextToken;
    }

    // ============================
    // FACULTY CRUD
    // ============================

    public function test_admin_can_create_faculty()
    {
        $this->withToken($this->adminToken)
             ->postJson('/api/faculties', ['name' => 'Faculty of Medicine', 'code' => 'FM', 'is_active' => true])
             ->assertStatus(201)
             ->assertJsonStructure(['data' => ['id', 'name']]);

        $this->assertDatabaseHas('faculties', ['name' => 'Faculty of Medicine']);
    }

    public function test_admin_can_update_faculty()
    {
        $faculty = Faculty::factory()->create(['name' => 'Old Name']);

        $this->withToken($this->adminToken)
             ->putJson("/api/faculties/{$faculty->id}", ['name' => 'New Name'])
             ->assertStatus(200);

        $this->assertDatabaseHas('faculties', ['id' => $faculty->id, 'name' => 'New Name']);
    }

    public function test_admin_can_delete_faculty()
    {
        $faculty = Faculty::factory()->create(['name' => 'To Delete']);

        $this->withToken($this->adminToken)
             ->deleteJson("/api/faculties/{$faculty->id}")
             ->assertStatus(200);

        $this->assertDatabaseMissing('faculties', ['id' => $faculty->id]);
    }

    public function test_admin_can_toggle_faculty()
    {
        $faculty = Faculty::factory()->create(['is_active' => true]);

        $this->withToken($this->adminToken)
             ->postJson("/api/faculties/{$faculty->id}/toggle")
             ->assertStatus(200);

        $this->assertDatabaseHas('faculties', ['id' => $faculty->id, 'is_active' => false]);
    }

    public function test_get_all_faculties()
    {
        Faculty::factory()->count(3)->create();

        $this->withToken($this->adminToken)
             ->getJson('/api/faculties')
             ->assertStatus(200);
    }

    // ============================
    // COURSE CRUD
    // ============================

    public function test_admin_can_create_course()
    {
        $faculty = Faculty::factory()->create();
        $dept    = Department::factory()->create(['faculty_id' => $faculty->id]);

        $this->withToken($this->adminToken)
             ->postJson('/api/courses', [
                 'code'          => 'BIO101',
                 'name'          => 'General Biology',
                 'credits'       => 3,
                 'department_id' => $dept->id,
                 'level'         => 'L1',
                 'semester'      => 1,
                 'hours_per_week'=> 3,
             ])
             ->assertStatus(201)
             ->assertJsonStructure(['data' => ['id', 'code', 'name']]);

        $this->assertDatabaseHas('courses', ['code' => 'BIO101']);
    }

    public function test_duplicate_course_code_returns_422()
    {
        $faculty = Faculty::factory()->create();
        $dept    = Department::factory()->create(['faculty_id' => $faculty->id]);
        Course::factory()->create(['code' => 'BIO101', 'department_id' => $dept->id]);

        $this->withToken($this->adminToken)
             ->postJson('/api/courses', [
                 'code'          => 'BIO101',
                 'name'          => 'Duplicate',
                 'credits'       => 3,
                 'department_id' => $dept->id,
                 'level'         => 'L1',
                 'semester'      => 1,
                 'hours_per_week'=> 3,
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['code']);
    }

    public function test_admin_can_read_single_course()
    {
        $course = Course::factory()->create();

        $this->withToken($this->adminToken)
             ->getJson("/api/courses/{$course->id}")
             ->assertStatus(200)
             ->assertJsonPath('data.id', $course->id);
    }

    public function test_nonexistent_course_returns_404()
    {
        $this->withToken($this->adminToken)
             ->getJson('/api/courses/99999')
             ->assertStatus(404);
    }

    public function test_admin_can_delete_course()
    {
        $course = Course::factory()->create();

        $this->withToken($this->adminToken)
             ->deleteJson("/api/courses/{$course->id}")
             ->assertStatus(200);

        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    // ============================
    // FEE TYPES CRUD
    // ============================

    public function test_finance_can_create_fee_type()
    {
        $this->withToken($this->financeToken)
             ->postJson('/api/fee-types', [
                 'name'        => 'Tuition Fee',
                 'description' => 'Annual tuition',
                 'amount'      => 500000,
                 'is_mandatory'=> true,
                 'level'       => 'L1',
             ])
             ->assertStatus(201)
             ->assertJsonStructure(['data' => ['id', 'name', 'amount']]);

        $this->assertDatabaseHas('fee_types', ['name' => 'Tuition Fee', 'amount' => 500000]);
    }

    public function test_fee_type_negative_amount_returns_422()
    {
        $this->withToken($this->financeToken)
             ->postJson('/api/fee-types', [
                 'name'        => 'Invalid Fee',
                 'amount'      => -100,
                 'is_mandatory'=> true,
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['amount']);
    }

    public function test_finance_can_update_fee_type()
    {
        $feeType = FeeType::factory()->create(['amount' => 100000]);

        $this->withToken($this->financeToken)
             ->putJson("/api/fee-types/{$feeType->id}", ['amount' => 150000])
             ->assertStatus(200);

        $this->assertDatabaseHas('fee_types', ['id' => $feeType->id, 'amount' => 150000]);
    }

    public function test_admin_can_delete_fee_type()
    {
        $feeType = FeeType::factory()->create();

        $this->withToken($this->adminToken)
             ->deleteJson("/api/fee-types/{$feeType->id}")
             ->assertStatus(200);

        $this->assertDatabaseMissing('fee_types', ['id' => $feeType->id]);
    }

    // ============================
    // STUDENT CREATION
    // ============================

    public function test_admin_can_create_student()
    {
        $faculty = Faculty::factory()->create();
        $dept    = Department::factory()->create(['faculty_id' => $faculty->id]);

        $this->withToken($this->adminToken)
             ->postJson('/api/students', [
                 'first_name'     => 'John',
                 'last_name'      => 'Doe',
                 'email'          => 'john.doe.test@esl.local',
                 'username'       => 'john.doe.test',
                 'password'       => 'Password123',
                 'department_id'  => $dept->id,
                 'level'          => 'L1',
                 'enrollment_date'=> now()->format('Y-m-d'),
             ])
             ->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe.test@esl.local',
            'role'  => 'student',
        ]);
    }

    public function test_duplicate_student_email_returns_422()
    {
        $faculty = Faculty::factory()->create();
        $dept    = Department::factory()->create(['faculty_id' => $faculty->id]);
        User::factory()->create(['email' => 'existing@esl.local', 'role' => 'student']);

        $this->withToken($this->adminToken)
             ->postJson('/api/students', [
                 'first_name'     => 'Another',
                 'last_name'      => 'Student',
                 'email'          => 'existing@esl.local',
                 'username'       => 'anotherstudent',
                 'password'       => 'Password123',
                 'department_id'  => $dept->id,
                 'level'          => 'L1',
                 'enrollment_date'=> now()->format('Y-m-d'),
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['email']);
    }

    public function test_registrar_can_create_student()
    {
        $faculty = Faculty::factory()->create();
        $dept    = Department::factory()->create(['faculty_id' => $faculty->id]);

        $this->withToken($this->registrarToken)
             ->postJson('/api/students', [
                 'first_name'     => 'Reg',
                 'last_name'      => 'Created',
                 'email'          => 'reg.created@esl.local',
                 'username'       => 'reg.created',
                 'password'       => 'Password123',
                 'department_id'  => $dept->id,
                 'level'          => 'L1',
                 'enrollment_date'=> now()->format('Y-m-d'),
             ])
             ->assertStatus(201);
    }

    // ============================
    // SETTINGS
    // ============================

    public function test_authenticated_user_can_get_settings()
    {
        $this->withToken($this->adminToken)
             ->getJson('/api/settings')
             ->assertStatus(200)
             ->assertJsonStructure(['settings']);
    }

    public function test_unauthenticated_cannot_get_settings()
    {
        $this->getJson('/api/settings')->assertStatus(401);
    }

    // ============================
    // PUBLIC ENDPOINTS
    // ============================

    public function test_public_settings_accessible_without_auth()
    {
        $this->getJson('/api/settings/public')->assertStatus(200);
    }

    public function test_login_route_is_public()
    {
        // Returns 422 (validation), not 401 — confirms route is publicly accessible
        $this->postJson('/api/login', ['username' => 'x', 'password' => 'y'])
             ->assertStatus(422);
    }
}
