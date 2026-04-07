<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FacultyController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\FeeTypeController;
use App\Http\Controllers\Api\StudentFeeController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\ELearningController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\RegistrarController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SettingsController;

// ==================== PUBLIC ROUTES ====================
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify-login-otp', [AuthController::class, 'verifyLoginOtp']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/settings/public', [SettingsController::class, 'publicSettings']);

// ==================== AUTHENTICATED ROUTES ====================
Route::middleware('auth:sanctum')->group(function () {

    // --- Auth (all authenticated users) ---
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/change-password', [AuthController::class, 'changePassword']);

    // --- Notifications (all authenticated users) ---
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::delete('/notifications/all', [NotificationController::class, 'destroyAll']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // --- Settings (all authenticated users) ---
    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingsController::class, 'getSettings']);
        Route::put('/', [SettingsController::class, 'updateSettings']);
        Route::post('/reset', [SettingsController::class, 'resetSettings']);
        Route::get('/widgets', [SettingsController::class, 'getAvailableWidgets']);
    });

    // --- Announcements (all authenticated users can read) ---
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::get('/announcements-active', [AnnouncementController::class, 'active']);
    Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show']);

    // ==================== DASHBOARDS (role-specific) ====================
    Route::prefix('dashboard')->group(function () {
        Route::get('/admin', [DashboardController::class, 'adminStats'])
            ->middleware('role:admin');
        Route::get('/student', [DashboardController::class, 'studentStats'])
            ->middleware('role:student');
        Route::get('/teacher', [DashboardController::class, 'teacherStats'])
            ->middleware('role:teacher');
        Route::get('/finance', [DashboardController::class, 'financeStats'])
            ->middleware('role:admin|finance');
        Route::get('/registrar', [DashboardController::class, 'registrarStats'])
            ->middleware('role:registrar');
    });

    // ==================== ACADEMIC READ (admin, registrar, teacher, student) ====================
    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/{course}', [CourseController::class, 'show']);
    Route::get('/classes', [ClassController::class, 'index']);
    Route::get('/classes/{class}', [ClassController::class, 'show']);
    Route::get('/classes/{class}/students', [ClassController::class, 'students']);
    Route::get('/faculties', [FacultyController::class, 'index']);
    Route::get('/faculties/{faculty}', [FacultyController::class, 'show']);
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::get('/departments/{department}', [DepartmentController::class, 'show']);

    // Student list read (finance needs it to assign/view fees; teachers need it for their classes)
    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/students/{student}', [StudentController::class, 'show']);

    // Student self-service (students can see their own data)
    Route::get('/students/{student}/courses', [StudentController::class, 'courses']);
    Route::get('/students/{student}/grades', [StudentController::class, 'grades']);
    Route::get('/students/{student}/attendance', [StudentController::class, 'attendance']);
    Route::get('/students/{student}/fees', [StudentController::class, 'fees']);

    // Teachers (read for all authenticated roles — teachers need to see their own classes)
    Route::get('/teachers/{teacher}/classes', [TeacherController::class, 'classes']);
    Route::get('/teachers/{teacher}/students', [TeacherController::class, 'students']);

    // Schedules (read for all roles)
    Route::get('/schedules', [ScheduleController::class, 'index']);
    Route::get('/schedules/{schedule}', [ScheduleController::class, 'show']);
    Route::get('/schedules/student/{studentId}', [ScheduleController::class, 'byStudent']);
    Route::get('/schedules/teacher/{teacherId}', [ScheduleController::class, 'byTeacher']);

    // Grades (read for teacher and admin)
    Route::get('/grades', [GradeController::class, 'index']);
    Route::get('/grades/{grade}', [GradeController::class, 'show']);
    Route::get('/grades/class/{classId}', [GradeController::class, 'byClass']);

    // Attendance (read for teacher and admin)
    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::get('/attendance/{attendance}', [AttendanceController::class, 'show']);
    Route::get('/attendance/class/{classId}', [AttendanceController::class, 'byClass']);
    Route::get('/attendance/class/{classId}/statistics', [AttendanceController::class, 'statistics']);

    // ==================== ADMIN MANAGEMENT ====================
    Route::middleware('role:admin')->group(function () {
        // Faculties & Departments (write)
        Route::post('/faculties', [FacultyController::class, 'store']);
        Route::put('/faculties/{faculty}', [FacultyController::class, 'update']);
        Route::delete('/faculties/{faculty}', [FacultyController::class, 'destroy']);
        Route::post('/faculties/{faculty}/toggle', [FacultyController::class, 'toggle']);
        Route::post('/departments', [DepartmentController::class, 'store']);
        Route::put('/departments/{department}', [DepartmentController::class, 'update']);
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy']);
        Route::post('/departments/{department}/toggle', [DepartmentController::class, 'toggle']);

        // Courses (write)
        Route::post('/courses', [CourseController::class, 'store']);
        Route::put('/courses/{course}', [CourseController::class, 'update']);
        Route::delete('/courses/{course}', [CourseController::class, 'destroy']);
        Route::post('/courses/{course}/toggle', [CourseController::class, 'toggle']);

        // Classes (write)
        Route::post('/classes', [ClassController::class, 'store']);
        Route::put('/classes/{class}', [ClassController::class, 'update']);
        Route::delete('/classes/{class}', [ClassController::class, 'destroy']);
        Route::post('/classes/{class}/assign-teacher', [ClassController::class, 'assignTeacher']);

        // Schedules (write)
        Route::post('/schedules', [ScheduleController::class, 'store']);
        Route::put('/schedules/{schedule}', [ScheduleController::class, 'update']);
        Route::delete('/schedules/{schedule}', [ScheduleController::class, 'destroy']);

        // Announcements (write - admin only)
        Route::post('/announcements', [AnnouncementController::class, 'store']);
        Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update']);
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy']);

        // Activity Logs (admin only)
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        Route::get('/activity-logs/actions', [ActivityLogController::class, 'actions']);
        Route::get('/activity-logs/{activityLog}', [ActivityLogController::class, 'show']);

        // Admin Grade Management
        Route::prefix('admin')->group(function () {
            Route::get('/students/search', [AdminController::class, 'searchStudents']);
            Route::get('/students/{id}/details', [AdminController::class, 'getStudentDetails']);
            Route::get('/students/{id}/report', [AdminController::class, 'getStudentReport']);
            // Two separate external sheets (files)
            Route::get('/students/{id}/report/sheet/academic', [AdminController::class, 'viewStudentAcademicSheet']);
            Route::get('/students/{id}/report/download/academic', [AdminController::class, 'downloadStudentAcademicSheet']);
            Route::get('/students/{id}/report/sheet/financial', [AdminController::class, 'viewStudentFinancialSheet']);
            Route::get('/students/{id}/report/download/financial', [AdminController::class, 'downloadStudentFinancialSheet']);
            Route::get('/grades/overview', [GradeController::class, 'adminOverview']);
            Route::post('/grades/validate-class/{classId}', [GradeController::class, 'validateClass']);
            Route::post('/grades/reject-class/{classId}', [GradeController::class, 'rejectClass']);
            Route::put('/grades/{gradeId}', [AdminController::class, 'updateGrade']);
            Route::get('/grades/{gradeId}/history', [AdminController::class, 'getGradeHistory']);
            Route::get('/students/{studentId}/courses', [AdminController::class, 'getStudentCourses']);
            Route::post('/students/{studentId}/courses', [AdminController::class, 'addStudentCourse']);
            Route::delete('/students/{studentId}/courses/{courseId}', [AdminController::class, 'removeStudentCourse']);
            Route::post('/students/{studentId}/transfer-grade', [AdminController::class, 'addTransferGrade']);
            Route::post('/students/{studentId}/equivalences', [AdminController::class, 'addCourseEquivalence']);
            Route::put('/equivalences/{equivalenceId}/review', [AdminController::class, 'reviewEquivalence']);
            Route::get('/kpis', [AdminController::class, 'getKPIs']);
            Route::get('/alerts', [AdminController::class, 'getStudentAlerts']);
        });

        // Chatbot admin-only routes
        Route::get('/chatbot/search-student', [ChatbotController::class, 'searchStudent']);
        Route::get('/chatbot/student/{id}', [ChatbotController::class, 'getStudentDetails']);
    });

    // ==================== ADMIN + REGISTRAR ====================
    Route::middleware('role:admin|registrar')->group(function () {
        // Students CRUD (GET index/show are in ACADEMIC READ — accessible to all roles)
        Route::post('/students', [StudentController::class, 'store']);
        Route::put('/students/{student}', [StudentController::class, 'update']);
        Route::delete('/students/{student}', [StudentController::class, 'destroy']);
        Route::post('/students/{student}/auto-enroll', [StudentController::class, 'autoEnroll']);
        Route::post('/students/auto-enroll-all', [StudentController::class, 'autoEnrollAll']);
        Route::post('/students/{student}/promote', [StudentController::class, 'promoteToNextLevel']);
        Route::post('/students/{student}/advance-semester', [StudentController::class, 'advanceSemester']);

        // Teachers CRUD
        Route::get('/teachers', [TeacherController::class, 'index']);
        Route::post('/teachers', [TeacherController::class, 'store']);
        Route::get('/teachers/{teacher}', [TeacherController::class, 'show']);
        Route::put('/teachers/{teacher}', [TeacherController::class, 'update']);
        Route::delete('/teachers/{teacher}', [TeacherController::class, 'destroy']);

        // Enrollments
        Route::get('/enrollments', [EnrollmentController::class, 'index']);
        Route::post('/enrollments', [EnrollmentController::class, 'store']);
        Route::get('/enrollments/{enrollment}', [EnrollmentController::class, 'show']);
        Route::put('/enrollments/{enrollment}', [EnrollmentController::class, 'update']);
        Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy']);
        Route::put('/enrollments/{enrollment}/status', [EnrollmentController::class, 'updateStatus']);
    });

    // ==================== ADMIN + TEACHER (Grades & Attendance write) ====================
    Route::middleware('role:admin|teacher')->group(function () {
        Route::post('/grades', [GradeController::class, 'store']);
        Route::put('/grades/{grade}', [GradeController::class, 'update']);
        Route::delete('/grades/{grade}', [GradeController::class, 'destroy']);
        Route::post('/grades/bulk', [GradeController::class, 'bulkUpdate']);
        Route::post('/grades/submit-class/{classId}', [GradeController::class, 'submitToAdmin']);
        Route::post('/attendance', [AttendanceController::class, 'store']);
        Route::put('/attendance/{attendance}', [AttendanceController::class, 'update']);
        Route::delete('/attendance/{attendance}', [AttendanceController::class, 'destroy']);
        Route::post('/attendance/bulk', [AttendanceController::class, 'bulkMark']);
    });

    // ==================== ADMIN + FINANCE ====================
    Route::middleware('role:admin|finance')->group(function () {
        // Fee Types
        Route::get('/fee-types', [FeeTypeController::class, 'index']);
        Route::post('/fee-types', [FeeTypeController::class, 'store']);
        Route::get('/fee-types/{feeType}', [FeeTypeController::class, 'show']);
        Route::put('/fee-types/{feeType}', [FeeTypeController::class, 'update']);
        Route::delete('/fee-types/{feeType}', [FeeTypeController::class, 'destroy']);
        Route::post('/fee-types/{feeType}/toggle', [FeeTypeController::class, 'toggle']);

        // Student Fees
        Route::get('/student-fees', [StudentFeeController::class, 'index']);
        Route::post('/student-fees', [StudentFeeController::class, 'store']);
        Route::get('/student-fees/{studentFee}', [StudentFeeController::class, 'show']);
        Route::put('/student-fees/{studentFee}', [StudentFeeController::class, 'update']);
        Route::delete('/student-fees/{studentFee}', [StudentFeeController::class, 'destroy']);
        Route::get('/student-fees/student/{studentId}', [StudentFeeController::class, 'byStudent']);
        Route::post('/student-fees/assign-all', [StudentFeeController::class, 'assignToAll']);
        Route::put('/student-fees/{studentFee}/installment-plan', [StudentFeeController::class, 'setInstallmentPlan']);

        // Payments (finance management)
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::post('/payments', [PaymentController::class, 'store']);
        Route::get('/payments/{payment}', [PaymentController::class, 'show']);
        Route::put('/payments/{payment}', [PaymentController::class, 'update']);
        Route::delete('/payments/{payment}', [PaymentController::class, 'destroy']);
        Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt']);
        Route::get('/payments-today', [PaymentController::class, 'todayCollection']);
    });

    // ==================== STUDENT PAYMENTS ====================
    Route::middleware('role:student')->prefix('payment')->group(function () {
        Route::get('/summary', [PaymentController::class, 'getFeeSummary']);
        Route::get('/history', [PaymentController::class, 'getPaymentHistory']);
        Route::post('/initialize', [PaymentController::class, 'initializePayment']);
        Route::get('/status/{reference}', [PaymentController::class, 'checkPaymentStatus']);
        Route::get('/receipt/{paymentId}', [PaymentController::class, 'downloadReceipt']);
    });

    // ==================== CHATBOT (all authenticated) ====================
    Route::prefix('chatbot')->group(function () {
        Route::post('/', [ChatbotController::class, 'chat']);
        Route::get('/history', [ChatbotController::class, 'getHistory']);
        // Admin-only routes are declared above in the admin middleware group
    });

    // ==================== E-LEARNING PLATFORM ====================
    Route::prefix('elearning')->group(function () {
        // Teacher's classes/courses
        Route::get('/teacher/classes', [ELearningController::class, 'getTeacherClasses']);
        Route::get('/courses/teacher', [ELearningController::class, 'getTeacherCourses']);
        Route::get('/courses/student', [ELearningController::class, 'getStudentCourses']);
        Route::post('/courses', [ELearningController::class, 'createOnlineCourse']);
        Route::post('/courses/{id}/join', [ELearningController::class, 'joinOnlineCourse']);
        Route::post('/courses/{id}/leave', [ELearningController::class, 'leaveOnlineCourse']);
        Route::post('/courses/{id}/start', [ELearningController::class, 'startOnlineCourse']);
        Route::post('/courses/{id}/end', [ELearningController::class, 'endOnlineCourse']);
        Route::put('/courses/{id}', [ELearningController::class, 'updateOnlineCourse']);
        Route::post('/materials', [ELearningController::class, 'uploadMaterial']);
        Route::get('/materials/course/{courseId}', [ELearningController::class, 'getCourseMaterials']);
        Route::get('/materials/{id}/download', [ELearningController::class, 'downloadMaterial']);
        Route::delete('/materials/{id}', [ELearningController::class, 'deleteMaterial']);
        Route::post('/quizzes', [ELearningController::class, 'createQuiz']);
        Route::get('/quizzes/course/{courseId}', [ELearningController::class, 'getCourseQuizzes']);
        Route::post('/quizzes/{id}/start', [ELearningController::class, 'startQuiz']);
        Route::post('/quizzes/{id}/publish', [ELearningController::class, 'publishQuiz']);
        Route::get('/quizzes/{id}/results', [ELearningController::class, 'getQuizResults']);
        Route::delete('/quizzes/{id}', [ELearningController::class, 'deleteQuiz']);
        Route::post('/quizzes/attempt/{attemptId}/submit', [ELearningController::class, 'submitQuiz']);
        Route::post('/quizzes/attempt/{attemptId}/tab-switch', [ELearningController::class, 'reportTabSwitch']);
        Route::post('/assignments', [ELearningController::class, 'createAssignment']);
        Route::get('/assignments/course/{courseId}', [ELearningController::class, 'getCourseAssignments']);
        Route::post('/assignments/{id}/submit', [ELearningController::class, 'submitAssignment']);
        Route::post('/assignments/{id}/publish', [ELearningController::class, 'publishAssignment']);
        Route::get('/assignments/{id}/submissions', [ELearningController::class, 'getAssignmentSubmissions']);
        Route::delete('/assignments/{id}', [ELearningController::class, 'deleteAssignment']);
        Route::post('/assignments/submission/{submissionId}/grade', [ELearningController::class, 'gradeSubmission']);
        Route::get('/assignments/submission/{submissionId}/download', [ELearningController::class, 'downloadSubmission']);
    });

    // ==================== UNIFIED MANAGEMENT (Admin only) ====================
    Route::middleware('role:admin')->group(function () {
        Route::prefix('student-management')->group(function () {
            Route::get('/{student}/profile', [StudentController::class, 'getFullProfile']);
            Route::get('/{student}/available-courses', [StudentController::class, 'getAvailableCourses']);
            Route::get('/{student}/enrollment-history', [StudentController::class, 'getEnrollmentHistory']);
            Route::post('/{student}/assign-course', [StudentController::class, 'assignCourse']);
            Route::post('/{student}/bulk-assign-courses', [StudentController::class, 'bulkAssignCourses']);
            Route::delete('/{student}/remove-course/{enrollmentId}', [StudentController::class, 'removeCourse']);
        });

        Route::prefix('teacher-management')->group(function () {
            Route::get('/{teacher}/profile', [TeacherController::class, 'getFullProfile']);
            Route::get('/{teacher}/available-courses', [TeacherController::class, 'getAvailableCourses']);
            Route::get('/{teacher}/assigned-courses', [TeacherController::class, 'getAssignedCourses']);
            Route::get('/{teacher}/workload', [TeacherController::class, 'getWorkload']);
            Route::post('/{teacher}/assign-course', [TeacherController::class, 'assignCourse']);
            Route::post('/{teacher}/bulk-assign-courses', [TeacherController::class, 'bulkAssignCourses']);
            Route::delete('/{teacher}/remove-course/{classId}', [TeacherController::class, 'removeCourse']);
        });
    });
});

// ==================== REGISTRAR USER MANAGEMENT ====================
Route::middleware(['auth:sanctum', 'role:admin|registrar'])->prefix('registrar')->group(function () {
    Route::get('/users', [RegistrarController::class, 'listUsers']);
    Route::post('/users', [RegistrarController::class, 'createUser']);
    Route::delete('/users/{id}', [RegistrarController::class, 'deleteUser']);
    Route::post('/users/{id}/reset-password', [RegistrarController::class, 'resetPassword']);
    Route::put('/users/{id}/profile', [RegistrarController::class, 'updateUserProfile']);
    Route::post('/users/{id}/profile', [RegistrarController::class, 'updateUserProfile']);
});

// ==================== PAYMENT WEBHOOK (External providers - no auth) ====================
Route::post('/payment/webhook', [PaymentController::class, 'confirmPayment']);
