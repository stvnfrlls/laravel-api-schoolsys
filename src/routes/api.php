<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\GradingComponentController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\StudentGradeController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\GradeLevelController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\UserAddressController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// -------------------------------------------------------------------------
// Public
// -------------------------------------------------------------------------
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// -------------------------------------------------------------------------
// Any authenticated user
// -------------------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [UserController::class, 'profile']);
    Route::put('/profile', [UserController::class, 'updateProfile']);

    // Read-only: all authenticated roles can view grades and sections
    Route::get('/grade-levels', [GradeLevelController::class, 'index']);
    Route::get('/grade-levels/{gradeLevel}', [GradeLevelController::class, 'show']);
    Route::get('/sections', [SectionController::class, 'index']);
    Route::get('/sections/{section}', [SectionController::class, 'show']);

    // Read-only: all authenticated roles can view subjects
    Route::get('/subjects', [SubjectController::class, 'index']);
    Route::get('/subjects/{subject}', [SubjectController::class, 'show']);

    Route::get('/enrollments', [EnrollmentController::class, 'index']);
    Route::get('/enrollments/{enrollment}', [EnrollmentController::class, 'show']);
    Route::get('/sections/{section}/enrollments', [EnrollmentController::class, 'bySection']);

    Route::get('/users/{user}/address', [UserAddressController::class, 'show']);

    Route::get('/schedules', [ScheduleController::class, 'index']);
    Route::get('/schedules/{schedule}', [ScheduleController::class, 'show']);
    Route::get('/sections/{section}/schedules', [ScheduleController::class, 'bySection']);

    // Read-only: all authenticated roles can view grading components and student grades
    Route::get('/grading-components', [GradingComponentController::class, 'index']);
    Route::get('/grading-components/{gradingComponent}', [GradingComponentController::class, 'show']);
    Route::get('/student-grades', [StudentGradeController::class, 'index']);
    Route::get('/student-grades/{studentGrade}', [StudentGradeController::class, 'show']);
});

// -------------------------------------------------------------------------
// Student
// -------------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'role:student'])->group(function () {
    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/students/{student}', [StudentController::class, 'show']);
    Route::put('/students/{student}', [StudentController::class, 'update']);
});

// -------------------------------------------------------------------------
// Faculty
// -------------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'role:faculty'])->group(function () {
    // Faculty can input and update student grades for their assigned subjects
    Route::post('/student-grades', [StudentGradeController::class, 'store']);
    Route::put('/student-grades/{studentGrade}', [StudentGradeController::class, 'update']);
});

// -------------------------------------------------------------------------
// Sub-admin
// -------------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'role:sub-admin'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
});

// -------------------------------------------------------------------------
// Sub-admin OR Admin — shared section write access (no delete)
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

    Route::get('/teachers', [TeacherController::class, 'index']);
    Route::get('/teachers/{teacher}', [TeacherController::class, 'show']);
    Route::put('/teachers/{teacher}', [TeacherController::class, 'update']);

    Route::post('/schedules', [ScheduleController::class, 'store']);
    Route::put('/schedules/{schedule}', [ScheduleController::class, 'update']);

    // Sub-admin and admin can also input/update student grades
    Route::post('/student-grades', [StudentGradeController::class, 'store']);
    Route::put('/student-grades/{studentGrade}', [StudentGradeController::class, 'update']);
});

// -------------------------------------------------------------------------
// Admin only
// -------------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {

    // Full user management
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::patch('/users/{user}/activate', [UserController::class, 'activate']);
    Route::patch('/users/{user}/deactivate', [UserController::class, 'deactivate']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    // Role CRUD
    Route::apiResource('roles', RoleController::class);

    // Assign / remove / sync roles on a user
    Route::post('/users/{user}/roles', [RoleController::class, 'assignToUser']);
    Route::put('/users/{user}/roles', [RoleController::class, 'syncUserRoles']);
    Route::delete('/users/{user}/roles/{role}', [RoleController::class, 'removeFromUser']);

    // Grade levels: full control (structural — admin only)
    Route::post('/grade-levels', [GradeLevelController::class, 'store']);
    Route::put('/grade-levels/{gradeLevel}', [GradeLevelController::class, 'update']);
    Route::patch('/grade-levels/{gradeLevel}/activate', [GradeLevelController::class, 'activate']);
    Route::patch('/grade-levels/{gradeLevel}/deactivate', [GradeLevelController::class, 'deactivate']);
    Route::delete('/grade-levels/{gradeLevel}', [GradeLevelController::class, 'destroy']);

    // Sections: admin-only delete
    Route::delete('/sections/{section}', [SectionController::class, 'destroy']);

    // Subjects
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

    // Grading components: structural config — admin only for full control
    Route::post('/grading-components', [GradingComponentController::class, 'store']);
    Route::put('/grading-components/{gradingComponent}', [GradingComponentController::class, 'update']);
    Route::delete('/grading-components/{gradingComponent}', [GradingComponentController::class, 'destroy']);

    // Student grades: admin-only delete
    Route::delete('/student-grades/{studentGrade}', [StudentGradeController::class, 'destroy']);
});