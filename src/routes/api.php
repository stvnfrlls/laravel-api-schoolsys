<?php

use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AssignmentDetailController;
use App\Http\Controllers\Api\AssignmentQuestionController;
use App\Http\Controllers\Api\AssignmentSubmissionController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\GradingComponentController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\StudentGradeController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\GradeLevelController;
use App\Http\Controllers\Api\QuarterController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\UserAddressController;
use Illuminate\Support\Facades\Route;

// -------------------------------------------------------------------------
// Public
// -------------------------------------------------------------------------
Route::middleware('throttle:login')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});
// -------------------------------------------------------------------------
// Any authenticated user
// -------------------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [UserController::class, 'profile']);
    Route::put('/profile', [UserController::class, 'updateProfile']);

    Route::get('/grade-levels', [GradeLevelController::class, 'index']);
    Route::get('/grade-levels/{gradeLevel}', [GradeLevelController::class, 'show']);
    Route::get('/sections', [SectionController::class, 'index']);
    Route::get('/sections/{section}', [SectionController::class, 'show']);

    Route::get('/subjects', [SubjectController::class, 'index']);
    Route::get('/subjects/{subject}', [SubjectController::class, 'show']);

    Route::get('/enrollments', [EnrollmentController::class, 'index']);
    Route::get('/enrollments/{enrollment}', [EnrollmentController::class, 'show']);
    Route::get('/sections/{section}/enrollments', [EnrollmentController::class, 'bySection']);

    Route::get('/users/{user}/address', [UserAddressController::class, 'show']);

    Route::get('/schedules', [ScheduleController::class, 'index']);
    Route::get('/schedules/{schedule}', [ScheduleController::class, 'show']);
    Route::get('/sections/{section}/schedules', [ScheduleController::class, 'bySection']);

    Route::get('/grading-components', [GradingComponentController::class, 'index']);
    Route::get('/grading-components/{gradingComponent}', [GradingComponentController::class, 'show']);
    Route::get('/student-grades', [StudentGradeController::class, 'index']);
    Route::get('/student-grades/{studentGrade}', [StudentGradeController::class, 'show']);

    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::get('/attendance/flagged', [AttendanceController::class, 'flagged']);
    Route::get('/attendance/summary/{enrollment}', [AttendanceController::class, 'summary']);
    Route::get('/attendance/{attendance}', [AttendanceController::class, 'show']);

    Route::get('/quarter', [QuarterController::class, 'currentQuarter']);
    Route::get('/assignments/{assignment}', [AssignmentController::class, 'show']);
    Route::get('/assignments/{assignment}/submissions/my', [AssignmentSubmissionController::class, 'getMySubmission']);
    Route::get('/assignments/{assignment}/questions', [AssignmentQuestionController::class, 'index']);
});

// -------------------------------------------------------------------------
// Student self-service only (role: student)
// -------------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'role:student'])->group(function () {
    Route::get('/student/profile', [StudentController::class, 'myProfile']);
    Route::get('/student/schedule', [StudentController::class, 'mySchedule']);
    Route::get('/student/grades', [StudentController::class, 'myGrades']);
    Route::get('/student/attendance', [StudentController::class, 'myAttendance']);

    Route::get('/student-assignments', [AssignmentController::class, 'index']);
});

// -------------------------------------------------------------------------
// Faculty self-service (role: faculty)
// -------------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'role:faculty'])->group(function () {
    Route::post('/student-grades', [StudentGradeController::class, 'store']);
    Route::put('/student-grades/{studentGrade}', [StudentGradeController::class, 'update']);

    Route::post('/attendance', [AttendanceController::class, 'store']);
    Route::put('/attendance/{attendance}', [AttendanceController::class, 'update']);

    Route::get('/teacher/schedule', [ScheduleController::class, 'mySchedule']);
    Route::get('/teacher/subjects', [SubjectController::class, 'mySubjects']);
    Route::get('/teacher/sections', [SectionController::class, 'mySection']);
    Route::get('/teacher/profile', [TeacherController::class, 'myProfile']);

    Route::get('/faculty-assignments', [AssignmentController::class, 'index']);
    Route::post('/faculty-assignments', [AssignmentController::class, 'store']);
    Route::put('/faculty-assignments/{assignment}', [AssignmentController::class, 'update']);
    Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy']);

    Route::patch('/faculty/assignments/{assignment}/publish', [AssignmentController::class, 'togglePublish']);

    Route::get('/assignments/{assignment}/details', [AssignmentDetailController::class, 'show']);
    Route::put('/assignments/{assignment}/details', [AssignmentDetailController::class, 'update']);
});

// -------------------------------------------------------------------------
// Sub-admin only
// -------------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'role:sub-admin'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
});

// -------------------------------------------------------------------------
// Sub-admin OR Admin
// -------------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'role:sub-admin,admin'])->group(function () {
    Route::post('/sections', [SectionController::class, 'store']);
    Route::put('/sections/{section}', [SectionController::class, 'update']);
    Route::patch('/sections/{section}/activate', [SectionController::class, 'activate']);
    Route::patch('/sections/{section}/deactivate', [SectionController::class, 'deactivate']);

    Route::post('/enrollments', [EnrollmentController::class, 'store']);
    Route::put('/enrollments/{enrollment}', [EnrollmentController::class, 'update']);

    Route::post('/users/{user}/address', [UserAddressController::class, 'store']);
    Route::put('/users/{user}/address', [UserAddressController::class, 'update']);

    // Student management — mirrors teacher management
    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/students/{student}', [StudentController::class, 'show']);
    Route::put('/students/{student}', [StudentController::class, 'update']);

    Route::get('/teachers', [TeacherController::class, 'index']);
    Route::get('/teachers/{teacher}', [TeacherController::class, 'show']);
    Route::put('/teachers/{teacher}', [TeacherController::class, 'update']);

    Route::post('/schedules', [ScheduleController::class, 'store']);
    Route::put('/schedules/{schedule}', [ScheduleController::class, 'update']);

    Route::post('/student-grades', [StudentGradeController::class, 'store']);
    Route::put('/student-grades/{studentGrade}', [StudentGradeController::class, 'update']);

    Route::post('/attendance', [AttendanceController::class, 'store']);
    Route::put('/attendance/{attendance}', [AttendanceController::class, 'update']);

    Route::put('/quarter', [QuarterController::class, 'updateQuarter']);

    Route::get('/assignments', [AssignmentController::class, 'index']);
    Route::post('/assignments', [AssignmentController::class, 'store']);
    Route::put('/assignments/{assignment}', [AssignmentController::class, 'update']);
    Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy']);

    Route::patch('/assignments/{assignment}/publish', [AssignmentController::class, 'togglePublish']);

    Route::get('/assignments/{assignment}/details', [AssignmentDetailController::class, 'show']);
    Route::put('/assignments/{assignment}/details', [AssignmentDetailController::class, 'update']);
});

// -------------------------------------------------------------------------
// Admin only
// -------------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::patch('/users/{user}/activate', [UserController::class, 'activate']);
    Route::patch('/users/{user}/deactivate', [UserController::class, 'deactivate']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    Route::apiResource('roles', RoleController::class);
    Route::post('/users/{user}/roles', [RoleController::class, 'assignToUser']);
    Route::put('/users/{user}/roles', [RoleController::class, 'syncUserRoles']);
    Route::delete('/users/{user}/roles/{role}', [RoleController::class, 'removeFromUser']);

    Route::post('/grade-levels', [GradeLevelController::class, 'store']);
    Route::put('/grade-levels/{gradeLevel}', [GradeLevelController::class, 'update']);
    Route::patch('/grade-levels/{gradeLevel}/activate', [GradeLevelController::class, 'activate']);
    Route::patch('/grade-levels/{gradeLevel}/deactivate', [GradeLevelController::class, 'deactivate']);
    Route::delete('/grade-levels/{gradeLevel}', [GradeLevelController::class, 'destroy']);

    Route::delete('/sections/{section}', [SectionController::class, 'destroy']);

    Route::post('/subjects', [SubjectController::class, 'store']);
    Route::put('/subjects/{subject}', [SubjectController::class, 'update']);
    Route::patch('/subjects/{subject}/activate', [SubjectController::class, 'activate']);
    Route::patch('/subjects/{subject}/deactivate', [SubjectController::class, 'deactivate']);
    Route::post('subjects/{subject}/grade-levels', [SubjectController::class, 'assignToGradeLevel']);
    Route::delete('/subjects/{subject}/grade-levels/{gradeLevel}', [SubjectController::class, 'removeFromGradeLevel']);
    Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy']);

    Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy']);
    Route::delete('/users/{user}/address', [UserAddressController::class, 'destroy']);
    Route::delete('/schedules/{schedule}', [ScheduleController::class, 'destroy']);

    Route::post('/grading-components', [GradingComponentController::class, 'store']);
    Route::put('/grading-components/{gradingComponent}', [GradingComponentController::class, 'update']);
    Route::delete('/grading-components/{gradingComponent}', [GradingComponentController::class, 'destroy']);

    Route::delete('/student-grades/{studentGrade}', [StudentGradeController::class, 'destroy']);
    Route::delete('/attendance/{attendance}', [AttendanceController::class, 'destroy']);
});
// Assignment questions & submissions (faculty + admin access)
Route::middleware(['auth:sanctum', 'role:faculty,sub-admin,admin'])->group(function () {
    Route::prefix('assignments/{assignment}')->group(function () {
        Route::post('questions', [AssignmentQuestionController::class, 'store']);
        Route::put('questions/{question}', [AssignmentQuestionController::class, 'update']);
        Route::delete('questions/{question}', [AssignmentQuestionController::class, 'destroy']);
        Route::post('questions/reorder', [AssignmentQuestionController::class, 'reorder']);

        Route::get('submissions', [AssignmentSubmissionController::class, 'index']);
        Route::get('submissions/summary', [AssignmentSubmissionController::class, 'getSummary']);
        Route::get('submissions/{submission}', [AssignmentSubmissionController::class, 'show']);
        Route::post('submissions/{submission}/grade', [AssignmentSubmissionController::class, 'gradeAnswers']);
        Route::post('submissions/{submission}/push', [AssignmentSubmissionController::class, 'pushToGradebook']);
    });
});

// Assignment questions & submissions (student access)
Route::middleware(['auth:sanctum', 'role:student'])->group(function () {
    Route::prefix('assignments/{assignment}')->group(function () {
        Route::get('submissions/my', [AssignmentSubmissionController::class, 'getMySubmission']);
        Route::post('submissions/draft', [AssignmentSubmissionController::class, 'saveDraft']);
        Route::post('submissions/submit', [AssignmentSubmissionController::class, 'submit']);
        Route::post('submissions/resubmit', [AssignmentSubmissionController::class, 'resubmit']);
    });
});
