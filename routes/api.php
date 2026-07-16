<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\StudentController as AdminStudentController;
use App\Http\Controllers\Api\Admin\InstructorController as AdminInstructorController;
use App\Http\Controllers\Api\Admin\ScheduleController as AdminScheduleController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\CertificationController as AdminCertificationController;
use App\Http\Controllers\Api\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\Instructor\DashboardController as InstructorDashboardController;
use App\Http\Controllers\Api\Instructor\AttendanceController as InstructorAttendanceController;
use App\Http\Controllers\Api\Instructor\FeedbackController as InstructorFeedbackController;
use App\Http\Controllers\Api\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Api\Student\EnrollController as StudentEnrollController;
use App\Http\Controllers\Api\Student\PaymentController as StudentPaymentController;
use App\Http\Controllers\Api\Student\ScheduleController as StudentScheduleController;
use App\Http\Controllers\Api\Student\ProgressController as StudentProgressController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('api.role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('api.admin.dashboard');
        Route::apiResource('students', AdminStudentController::class)->except(['create', 'edit']);
        Route::post('/students/{student}/toggle-active', [AdminStudentController::class, 'toggleActive'])->name('api.admin.students.toggle');
        Route::post('/users/{user}/set-role', [AdminUserController::class, 'setRole'])->name('api.admin.users.set-role');
        Route::apiResource('instructors', AdminInstructorController::class)->except(['create', 'edit', 'show', 'update']);
        Route::post('/instructors/{instructor}/toggle-active', [AdminInstructorController::class, 'toggleActive'])->name('api.admin.instructors.toggle');
        Route::apiResource('schedules', AdminScheduleController::class)->except(['create', 'edit']);
        Route::post('/schedules/{schedule}/cancel', [AdminScheduleController::class, 'cancel'])->name('api.admin.schedules.cancel');
        Route::get('/payments', [AdminPaymentController::class, 'index'])->name('api.admin.payments.index');
        Route::post('/payments/{payment}/verify', [AdminPaymentController::class, 'verify'])->name('api.admin.payments.verify');
        Route::post('/payments/{payment}/reject', [AdminPaymentController::class, 'reject'])->name('api.admin.payments.reject');
        Route::apiResource('certifications', AdminCertificationController::class)->except(['create', 'edit', 'update']);
        Route::get('/certifications/courses-for/{student}', [AdminCertificationController::class, 'coursesFor'])->name('api.admin.certifications.courses-for');
        Route::post('/certifications/{certification}/revoke', [AdminCertificationController::class, 'revoke'])->name('api.admin.certifications.revoke');
        Route::get('/reports', [AdminReportController::class, 'index'])->name('api.admin.reports');
    });

    Route::middleware('api.role:instructor')->prefix('instructor')->group(function () {
        Route::get('/dashboard', [InstructorDashboardController::class, 'index'])->name('api.instructor.dashboard');
        Route::get('/attendance', [InstructorAttendanceController::class, 'index'])->name('api.instructor.attendance.index');
        Route::get('/attendance/{schedule}', [InstructorAttendanceController::class, 'show'])->name('api.instructor.attendance.show');
        Route::post('/attendance/{schedule}', [InstructorAttendanceController::class, 'store'])->name('api.instructor.attendance.store');
        Route::get('/feedback', [InstructorFeedbackController::class, 'index'])->name('api.instructor.feedback.index');
        Route::post('/feedback/{schedule}/{student}', [InstructorFeedbackController::class, 'store'])->name('api.instructor.feedback.store');
    });

    Route::middleware('api.role:student')->prefix('student')->group(function () {
        Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('api.student.dashboard');
        Route::get('/enroll', [StudentEnrollController::class, 'index'])->name('api.student.enroll.index');
        Route::post('/enroll/cart', [StudentEnrollController::class, 'cartEnroll'])->name('api.student.enroll.cart');
        Route::post('/enroll/{course}', [StudentEnrollController::class, 'enroll'])->name('api.student.enroll.store');
        Route::get('/payments', [StudentPaymentController::class, 'index'])->name('api.student.payments.index');
        Route::post('/payments/cart', [StudentPaymentController::class, 'cartStore'])->name('api.student.payments.cart');
        Route::get('/payments/return', [StudentPaymentController::class, 'returnVerify'])->name('api.student.payments.return');
        Route::get('/payments/{enrollment}/create', [StudentPaymentController::class, 'create'])->name('api.student.payments.create');
        Route::post('/payments/{enrollment}', [StudentPaymentController::class, 'store'])->name('api.student.payments.store');
        Route::get('/schedules', [StudentScheduleController::class, 'index'])->name('api.student.schedules.index');
        Route::post('/schedules/{schedule}/book', [StudentScheduleController::class, 'book'])->name('api.student.schedules.book');
        Route::delete('/schedules/{schedule}/book', [StudentScheduleController::class, 'cancel'])->name('api.student.schedules.cancel');
        Route::get('/progress', [StudentProgressController::class, 'index'])->name('api.student.progress');
    });
});

/* Public PayMongo webhook (no auth). */
Route::post('/webhooks/paymongo', [WebhookController::class, 'paymongo'])->name('api.webhooks.paymongo');
