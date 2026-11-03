<?php
/* One-off: fabricate a failed TDC assessment for the E2E retake test. */

$student = App\Models\Student::find(1);
$instructor = App\Models\Instructor::first() ?: App\Models\Instructor::create(['user_id' => 2]);
$course = App\Models\Course::where('type', 'TDC')->first();

if (! $course) {
    echo 'NO TDC COURSE IN DB (fine — will use fallback)';
    exit;
}

$schedule = App\Models\Schedule::create([
    'course_id' => $course->id,
    'instructor_id' => $instructor->id,
    'date' => now()->subDays(7)->toDateString(),
    'end_date' => now()->subDays(6)->toDateString(),
    'time_start' => '08:00:00',
    'time_end' => '17:00:00',
    'venue' => 'E2E classroom',
    'max_students' => 30,
    'status' => 'done',
]);

App\Models\StudentSchedule::create([
    'student_id' => $student->id,
    'schedule_id' => $schedule->id,
    'booked_at' => now()->subDays(8),
    'status' => 'done',
]);

$feedback = App\Models\Feedback::create([
    'instructor_id' => $instructor->id,
    'student_id' => $student->id,
    'schedule_id' => $schedule->id,
    'raw_text' => 'Failed the written exam with 50 percent. Needs to review road signs and traffic rules.',
    'exam_score' => 50,
]);

$assessment = App\Models\Assessment::create([
    'feedback_id' => $feedback->id,
    'classification' => 'needs',
    'confidence_score' => 0.7,
    'exam_score' => 50,
    'score' => 50,
    'passed' => false,
]);

echo json_encode([
    'schedule_id' => $schedule->id,
    'feedback_id' => $feedback->id,
    'assessment_id' => $assessment->id,
    'passed' => $assessment->passed,
]);
