<?php

use App\Http\Controllers\Admin\CertificationController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\InstructorController;
use App\Http\Controllers\Admin\PaymentController as AdminPayment;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Instructor\AttendanceController;
use App\Http\Controllers\Instructor\DashboardController as InstructorDashboard;
use App\Http\Controllers\Instructor\FeedbackController;
use App\Http\Controllers\Student\DashboardController as StudentDashboard;
use App\Http\Controllers\Student\EnrollController;
use App\Http\Controllers\Student\PaymentController as StudentPayment;
use App\Http\Controllers\Student\ProgressController;
use App\Http\Controllers\Student\ScheduleController as StudentBookingController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/frontend/index.html'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);
});

Route::post('/logout', [LogoutController::class, 'logout'])->name('logout')->middleware('auth');

Route::post('/webhook/paymongo', [WebhookController::class, 'paymongo'])->name('webhook.paymongo');

Route::middleware('auth')->group(function () {

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboard::class, 'index'])->name('dashboard');
        Route::resource('students', StudentController::class)->except(['store']);
        Route::post('/students/{student}/toggle', [StudentController::class, 'toggleActive'])->name('students.toggle');
        Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');

        Route::resource('instructors', InstructorController::class)->except(['show', 'update', 'destroy']);
        Route::post('/instructors/{instructor}/toggle', [InstructorController::class, 'toggleActive'])->name('instructors.toggle');

        Route::resource('schedules', ScheduleController::class)->except(['show', 'update', 'edit']);
        Route::post('/schedules/{schedule}/cancel', [ScheduleController::class, 'cancel'])->name('schedules.cancel');

        Route::get('/payments', [AdminPayment::class, 'index'])->name('payments.index');
        Route::post('/payments/{payment}/verify', [AdminPayment::class, 'verify'])->name('payments.verify');
        Route::post('/payments/{payment}/reject', [AdminPayment::class, 'reject'])->name('payments.reject');

        Route::resource('certifications', CertificationController::class)->except(['show', 'update', 'edit']);
        Route::post('/certifications/{certification}/revoke', [CertificationController::class, 'revoke'])->name('certifications.revoke');
        Route::get('/certifications/courses/{student}', [CertificationController::class, 'coursesFor'])->name('certifications.courses');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export/students', [ReportController::class, 'exportStudents'])->name('reports.export.students');
    });

    Route::middleware('role:instructor')->prefix('instructor')->name('instructor.')->group(function () {
        Route::get('/dashboard', [InstructorDashboard::class, 'index'])->name('dashboard');
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('/attendance/{schedule}', [AttendanceController::class, 'show'])->name('attendance.show');
        Route::post('/attendance/{schedule}', [AttendanceController::class, 'store'])->name('attendance.store');

        Route::get('/feedback', [FeedbackController::class, 'index'])->name('feedback.index');
        Route::get('/feedback/{schedule}/{student}/create', [FeedbackController::class, 'create'])->name('feedback.create');
        Route::post('/feedback/{schedule}/{student}', [FeedbackController::class, 'store'])->name('feedback.store');
    });

    Route::middleware('role:student')->prefix('student')->name('student.')->group(function () {
        Route::get('/dashboard', [StudentDashboard::class, 'index'])->name('dashboard');
        Route::get('/enroll', [EnrollController::class, 'index'])->name('enroll.index');
        Route::post('/enroll/{course}', [EnrollController::class, 'enroll'])->name('enroll');

        Route::get('/payment/{enrollment}', [StudentPayment::class, 'create'])->name('payment.create');
        Route::post('/payment/{enrollment}', [StudentPayment::class, 'store'])->name('payment.store');

        Route::get('/schedule', [StudentBookingController::class, 'index'])->name('schedule.index');
        Route::post('/schedule/{schedule}/book', [StudentBookingController::class, 'book'])->name('schedule.book');

        Route::get('/progress', [ProgressController::class, 'index'])->name('progress');
    });
});
