<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\StudentController as AdminStudentController;
use App\Http\Controllers\Api\Admin\InstructorController as AdminInstructorController;
use App\Http\Controllers\Api\Admin\ScheduleController as AdminScheduleController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\CertificationController as AdminCertificationController;
use App\Http\Controllers\Api\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\Admin\RequestController as AdminRequestController;
use App\Http\Controllers\Api\Admin\NlpController as AdminNlpController;
use App\Http\Controllers\Api\Student\RequestController as StudentRequestController;
use App\Http\Controllers\Api\Student\ChatController as StudentChatController;
use App\Http\Controllers\Api\Student\KycController as StudentKycController;
use App\Http\Controllers\Api\Admin\KycController as AdminKycController;
use App\Http\Controllers\Api\Admin\ChatController as AdminChatController;
use App\Http\Controllers\Api\Admin\TdcReviewController as AdminTdcReviewController;
use App\Http\Controllers\Api\Student\TdcReviewController as StudentTdcReviewController;
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

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
/* Public catalogue for the landing page pricing (never auth-gated). */
Route::get('/courses', [CourseController::class, 'index']);
/* Self-service password reset — picks Gmail (email) or SMS delivery. */
Route::post('/password/forgot', [PasswordResetController::class, 'request'])->middleware('throttle:login');
Route::post('/password/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/me/preferences', [AuthController::class, 'updatePreferences']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
        Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar']);
        Route::post('/profile/password', [ProfileController::class, 'updatePassword']);
        Route::post('/profile/verify-email', [ProfileController::class, 'sendVerification']);
        Route::post('/profile/verify-email/confirm', [ProfileController::class, 'confirmVerification']);
        Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('api.role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('api.admin.dashboard');
        Route::apiResource('students', AdminStudentController::class)->except(['create', 'edit']);
        Route::post('/students/{student}/toggle-active', [AdminStudentController::class, 'toggleActive'])->name('api.admin.students.toggle');
        Route::post('/students/{student}/assign-instructor', [AdminStudentController::class, 'assignInstructor'])->name('api.admin.students.assign');
        Route::get('/bookings', [AdminStudentController::class, 'pendingBookings'])->name('api.admin.bookings.index');
        Route::post('/bookings/{studentSchedule}/accept', [AdminStudentController::class, 'acceptBooking'])->name('api.admin.bookings.accept');
        Route::post('/bookings/{studentSchedule}/reject', [AdminStudentController::class, 'rejectBooking'])->name('api.admin.bookings.reject');
        Route::post('/users/{user}/set-role', [AdminUserController::class, 'setRole'])->name('api.admin.users.set-role');
        Route::get('/users/role-changes', [AdminUserController::class, 'roleChanges'])->name('api.admin.users.role-changes');
        Route::get('/users/{user}/profile', [ProfileController::class, 'adminShow'])->name('api.admin.users.profile.show');
        Route::put('/users/{user}/profile', [ProfileController::class, 'adminUpdate'])->name('api.admin.users.profile.update');
        Route::get('/notifications', [AdminReportController::class, 'notifications'])->name('api.admin.notifications');
        Route::get('/notifications/summary', [AdminReportController::class, 'notificationSummary'])->name('api.admin.notifications.summary');
        Route::post('/notifications/read', [AdminReportController::class, 'markNotificationsRead'])->name('api.admin.notifications.read');
        Route::post('/nlp/classify', [AdminNlpController::class, 'classify'])->name('api.admin.nlp.classify');
        Route::get('/nlp/evaluate', [AdminNlpController::class, 'evaluate'])->name('api.admin.nlp.evaluate');
        Route::apiResource('instructors', AdminInstructorController::class)->except(['create', 'edit', 'show', 'update']);
        Route::post('/instructors/{instructor}/toggle-active', [AdminInstructorController::class, 'toggleActive'])->name('api.admin.instructors.toggle');
        Route::get('/schedules', [AdminScheduleController::class, 'index'])->name('api.admin.schedules.index');
        Route::get('/schedules/{schedule}/students', [AdminScheduleController::class, 'students'])->name('api.admin.schedules.students');
        Route::get('/courses', [AdminScheduleController::class, 'courses'])->name('api.admin.courses');
        Route::post('/schedules', [AdminScheduleController::class, 'store'])->name('api.admin.schedules.store');
        Route::post('/schedules/{schedule}/cancel', [AdminScheduleController::class, 'cancel'])->name('api.admin.schedules.cancel');
        Route::post('/schedules/{schedule}/instructor', [AdminScheduleController::class, 'assignInstructor'])->name('api.admin.schedules.instructor');
        Route::get('/payments', [AdminPaymentController::class, 'index'])->name('api.admin.payments.index');
        Route::post('/payments', [AdminPaymentController::class, 'store'])->name('api.admin.payments.store');
        Route::post('/payments/{payment}/verify', [AdminPaymentController::class, 'verify'])->name('api.admin.payments.verify');
        Route::post('/payments/{payment}/reject', [AdminPaymentController::class, 'reject'])->name('api.admin.payments.reject');
        Route::get('/certifications/ready', [AdminCertificationController::class, 'ready'])->name('api.admin.certifications.ready');
        Route::get('/kyc', [AdminKycController::class, 'index'])->name('api.admin.kyc.index');
        Route::get('/kyc/{user}/file/{file}', [AdminKycController::class, 'file'])->name('api.admin.kyc.file');
        Route::post('/kyc/{user}/approve', [AdminKycController::class, 'approve'])->name('api.admin.kyc.approve');
        Route::post('/kyc/{user}/reject', [AdminKycController::class, 'reject'])->name('api.admin.kyc.reject');
        Route::get('/requests', [AdminRequestController::class, 'index'])->name('api.admin.requests.index');
        Route::post('/requests/{studentRequest}/accept', [AdminRequestController::class, 'accept'])->name('api.admin.requests.accept');
        Route::post('/requests/{studentRequest}/reject', [AdminRequestController::class, 'reject'])->name('api.admin.requests.reject');
        Route::get('/certifications', [AdminCertificationController::class, 'index'])->name('api.admin.certifications.index');
        Route::get('/certifications/{certification}', [AdminCertificationController::class, 'show'])->name('api.admin.certifications.show');
        Route::post('/certifications', [AdminCertificationController::class, 'store'])->name('api.admin.certifications.store');
        Route::get('/certifications/courses-for/{student}', [AdminCertificationController::class, 'coursesFor'])->name('api.admin.certifications.courses-for');
        Route::post('/certifications/{certification}/revoke', [AdminCertificationController::class, 'revoke'])->name('api.admin.certifications.revoke');
        Route::get('/reports', [AdminReportController::class, 'index'])->name('api.admin.reports');
        Route::post('/reports/cert-settings', [AdminReportController::class, 'updateCertSettings'])->name('api.admin.reports.cert-settings');
        Route::get('/attendance', [AdminScheduleController::class, 'attendanceIndex'])->name('api.admin.attendance.index');
        Route::get('/attendance/{schedule}', [AdminScheduleController::class, 'attendanceShow'])->name('api.admin.attendance.show');
        Route::get('/chat', [AdminChatController::class, 'index'])->name('api.admin.chat.threads');
        Route::get('/chat/{student}', [AdminChatController::class, 'show'])->name('api.admin.chat.thread');
        Route::post('/chat/{student}/reply', [AdminChatController::class, 'reply'])->name('api.admin.chat.reply');
        Route::get('/tdc-verification', [AdminTdcReviewController::class, 'index'])->name('api.admin.tdc.index');
        Route::get('/tdc-verification/{student}/file', [AdminTdcReviewController::class, 'file'])->name('api.admin.tdc.file');
        Route::post('/tdc-verification/{student}/approve', [AdminTdcReviewController::class, 'approve'])->name('api.admin.tdc.approve');
        Route::post('/tdc-verification/{student}/reject', [AdminTdcReviewController::class, 'reject'])->name('api.admin.tdc.reject');
    });

    Route::middleware('api.role:instructor')->prefix('instructor')->group(function () {
        Route::get('/dashboard', [InstructorDashboardController::class, 'index'])->name('api.instructor.dashboard');
        Route::get('/attendance', [InstructorAttendanceController::class, 'index'])->name('api.instructor.attendance.index');
        Route::get('/attendance/{schedule}', [InstructorAttendanceController::class, 'show'])->name('api.instructor.attendance.show');
        Route::post('/attendance/{schedule}', [InstructorAttendanceController::class, 'store'])->name('api.instructor.attendance.store');
        Route::get('/students', [InstructorDashboardController::class, 'students'])->name('api.instructor.students');
        Route::get('/feedback', [InstructorFeedbackController::class, 'index'])->name('api.instructor.feedback.index');
        Route::post('/feedback/{schedule}/{student}', [InstructorFeedbackController::class, 'store'])->name('api.instructor.feedback.store');
    });

    Route::middleware('api.role:student')->prefix('student')->group(function () {
        Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('api.student.dashboard');
        /* KYC identity verification. */
        Route::get('/kyc', [StudentKycController::class, 'show'])->name('api.student.kyc.show');
        Route::post('/kyc', [StudentKycController::class, 'store'])->middleware('throttle:10,1')->name('api.student.kyc.store');
        Route::get('/kyc/file/{file}', [StudentKycController::class, 'file'])->name('api.student.kyc.file');
        Route::get('/enroll', [StudentEnrollController::class, 'index'])->name('api.student.enroll.index');
        Route::post('/enroll/cart', [StudentEnrollController::class, 'cartEnroll'])->middleware('api.kyc')->name('api.student.enroll.cart');
        Route::get('/payments', [StudentPaymentController::class, 'index'])->name('api.student.payments.index');
        Route::post('/payments/cart', [StudentPaymentController::class, 'cartStore'])->middleware('api.kyc')->name('api.student.payments.cart');
        Route::get('/payments/return', [StudentPaymentController::class, 'returnVerify'])->name('api.student.payments.return');
        Route::get('/payments/{enrollment}/create', [StudentPaymentController::class, 'create'])->name('api.student.payments.create');
        Route::post('/payments/{enrollment}', [StudentPaymentController::class, 'store'])->middleware('api.kyc')->name('api.student.payments.store');
        Route::get('/schedules', [StudentScheduleController::class, 'index'])->name('api.student.schedules.index');
        Route::post('/schedules/{schedule}/book', [StudentScheduleController::class, 'book'])->middleware('api.kyc')->name('api.student.schedules.book');
        Route::delete('/schedules/{schedule}/book', [StudentScheduleController::class, 'cancel'])->name('api.student.schedules.cancel');
        Route::get('/schedules/pdc-calendar', [StudentScheduleController::class, 'pdcCalendar'])->name('api.student.schedules.pdcCalendar');
        Route::get('/schedules/pdc-day', [StudentScheduleController::class, 'pdcDay'])->name('api.student.schedules.pdcDay');
        Route::get('/progress', [StudentProgressController::class, 'index'])->name('api.student.progress');
        Route::get('/requests', [StudentRequestController::class, 'index'])->name('api.student.requests.index');
        Route::post('/requests/instructor-preference', [StudentRequestController::class, 'instructorPreference'])->name('api.student.requests.preference');
        Route::post('/requests/booking', [StudentRequestController::class, 'bookingRequest'])->name('api.student.requests.booking');
        Route::post('/requests/reschedule', [StudentRequestController::class, 'reschedule'])->name('api.student.requests.reschedule');
        Route::post('/requests/retake', [StudentRequestController::class, 'retake'])->name('api.student.requests.retake');
        Route::delete('/requests/{studentRequest}', [StudentRequestController::class, 'destroy'])->name('api.student.requests.destroy');
        Route::get('/chat', [StudentChatController::class, 'index'])->name('api.student.chat.index');
        Route::post('/chat', [StudentChatController::class, 'send'])->middleware('throttle:30,1')->name('api.student.chat.send');
        Route::get('/tdc-review', [StudentTdcReviewController::class, 'show'])->name('api.student.tdc.show');
        Route::post('/tdc-review', [StudentTdcReviewController::class, 'store'])->middleware('throttle:10,1')->name('api.student.tdc.store');
        Route::get('/tdc-review/file', [StudentTdcReviewController::class, 'file'])->name('api.student.tdc.file');
    });
});

/* Public PayMongo webhook (no auth). Throttled to blunt abuse; the signature is
   still verified inside the controller. */
Route::post('/webhooks/paymongo', [WebhookController::class, 'paymongo'])
    ->middleware('throttle:60,1')
    ->name('api.webhooks.paymongo');
