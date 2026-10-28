<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\Certification;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Feedback;
use App\Models\Instructor;
use App\Models\Payment;
use App\Models\Request;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentSchedule;
use App\Models\User;
use App\Services\AssessmentSuggestion;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/* Creates a realistic demo cohort so every admin panel has data:
   students, enrollments, payments spread across months, sessions,
   attendance, assessments, certificates, ID-verification states and
   student requests.

   Usage:
     php artisan db:seed --class=DummyStudentsSeeder
     $env:DUMMY_STUDENT_COUNT=250; php artisan db:seed --class=DummyStudentsSeeder

   Safe to re-run: it skips any student email that already exists. */
class DummyStudentsSeeder extends Seeder
{
    private const FIRST_MALE = [
        'Juan', 'Jose', 'Andres', 'Emilio', 'Gabriel', 'Rafael', 'Marco', 'Miguel',
        'Paolo', 'Lorenzo', 'Angelo', 'Joshua', 'Daniel', 'Nathaniel', 'Christian',
        'Kervin', 'Jipre', 'Cardo', 'Robin', 'Ramon', 'Eduardo', 'Ferdinand',
        'Bong', 'Arnel', 'Dennis',
    ];

    private const FIRST_FEMALE = [
        'Maria', 'Ana', 'Rosa', 'Liza', 'Cristina', 'Andrea', 'Bianca', 'Camille',
        'Danica', 'Erika', 'Faith', 'Grace', 'Hannah', 'Isabel', 'Jasmine',
        'Kathleen', 'Lorna', 'Mikaela', 'Nicole', 'Olivia', 'Patricia', 'Queenie',
        'Rhea', 'Sofia', 'Teresa',
    ];

    private const LAST = [
        'Cruz', 'Santos', 'Reyes', 'Dela Cruz', 'Bautista', 'Ocampo', 'Garcia',
        'Mendoza', 'Torres', 'Ramos', 'Aquino', 'Villanueva', 'Castillo', 'Flores',
        'Gonzales', 'Navarro', 'Salazar', 'Domingo', 'Ferrer', 'Lim',
    ];

    private const ADDRESSES = [
        'Quezon City', 'Manila', 'Makati City', 'Pasig City', 'Caloocan City',
        'Taguig City', 'Marikina City', 'Antipolo City', 'Bacoor, Cavite',
        'Dasmariñas, Cavite', 'San Fernando, Pampanga', 'Lipa City, Batangas',
    ];

    private const ID_TYPES = [
        'philippine_passport' => 'Philippine Passport',
        'drivers_license' => "Driver's License",
        'student_permit' => 'Student Permit',
        'philsys_id' => 'PhilSys / National ID',
        'umid' => 'UMID',
        'voters_id' => "Voter's ID",
        'postal_id' => 'Postal ID',
    ];

    private const REJECT_REASONS = [
        'The uploaded ID was blurry and the expiry date could not be read. Please upload a clearer photo.',
        'Only part of the ID was visible. Please upload a photo showing the whole front of the card.',
        'The ID had already expired. Please submit a valid, unexpired government ID.',
    ];

    private int $adminId;

    private string $passwordHash;

    private array $certSeq = [];

    /** @var array<int, Schedule> */
    private array $tdcPast = [];

    /** @var array<int, Schedule> */
    private array $pdcPast = [];

    /** @var array<int, Schedule> */
    private array $tdcUpcoming = [];

    /** @var array<int, Schedule> */
    private array $pdcUpcoming = [];

    private int $tdcPastCursor = 0;

    private int $pdcPastCursor = 0;

    public function run(): void
    {
        $count = (int) (env('DUMMY_STUDENT_COUNT') ?: 100);

        $admin = User::where('role', 'admin')->first();
        if (! $admin) {
            $this->command?->warn('No admin account found. Run `php artisan db:seed` first.');
            return;
        }
        $this->adminId = $admin->id;
        $this->passwordHash = Hash::make('student123');

        $this->ensureInstructors();

        $tdcFaceToFace = $this->course('TDC', 'Face to Face');
        $tdcOnline = $this->course('TDC', 'Online') ?? $tdcFaceToFace;
        $pdcFourWheels = $this->course('PDC', '4 Wheels');
        $pdcTwoWheels = $this->course('PDC', '2 Wheels') ?? $pdcFourWheels;

        if (! $tdcFaceToFace || ! $pdcFourWheels) {
            $this->command?->warn('Courses are missing. Run `php artisan db:seed` first.');
            return;
        }

        $instructors = Instructor::with('user')->get();

        $this->seedSchedules($tdcFaceToFace, $tdcOnline, $pdcFourWheels, $pdcTwoWheels, $instructors);

        $created = 0;

        for ($i = 1; $i <= $count; $i++) {
            $email = sprintf('student%03d@rcdriving.ph', $i);

            if (User::where('email', $email)->exists()) {
                continue;
            }

            $this->makeStudent($i, $email, $instructors, $tdcFaceToFace, $tdcOnline, $pdcFourWheels, $pdcTwoWheels);
            $created++;
        }

        $this->command?->info("Dummy students ready: {$created} created ({$count} requested).");
        $this->command?->info('Login for every dummy student: student001@rcdriving.ph … password: student123');
    }

    /* ------------------------------------------------------------------ */
    /* Students                                                            */
    /* ------------------------------------------------------------------ */

    private function makeStudent(
        int $i,
        string $email,
        $instructors,
        Course $tdcFaceToFace,
        Course $tdcOnline,
        Course $pdcFourWheels,
        Course $pdcTwoWheels
    ): void {
        $gender = $i % 2 === 0 ? 'male' : 'female';
        $firstPool = $gender === 'male' ? self::FIRST_MALE : self::FIRST_FEMALE;
        $first = $firstPool[intdiv($i, 2) % count($firstPool)];
        $last = self::LAST[intdiv($i - 1, 50) % count(self::LAST)];
        $name = $first . ' ' . $last;

        $joinedAt = now()->subDays(3 + (($i * 13) % 210))->startOfDay()->addHours(9);
        $instructor = $instructors[$i % max(1, $instructors->count())];

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $this->passwordHash,
            'role' => 'student',
            'is_active' => $i % 19 !== 0, // a few deactivated accounts for the admin list
        ]);
        $user->forceFill(['email_verified_at' => $joinedAt])->save();

        $student = Student::create([
            'user_id' => $user->id,
            'instructor_id' => $instructor->id,
            'phone' => '09' . str_pad((string) (170000000 + $i * 137), 9, '0', STR_PAD_LEFT),
            'address' => self::ADDRESSES[$i % count(self::ADDRESSES)],
            'birthdate' => sprintf('%04d-%02d-%02d', 1988 + ($i % 18), (($i * 3) % 12) + 1, (($i * 7) % 27) + 1),
            'license_type' => ['student-permit', 'non-pro', 'pro'][$i % 3],
            'gender' => $gender,
            'preferred_instructor_gender' => $i % 5 === 0 ? ($gender === 'male' ? 'female' : 'male') : null,
            'document_path' => null,
        ]);

        $idStatus = $this->scenario($student, $i, $joinedAt, $tdcFaceToFace, $tdcOnline, $pdcFourWheels, $pdcTwoWheels);

        $this->attachId($student, $idStatus, $i);
    }

    /* Twelve overlapping scenarios keep every panel populated without making
       the data look fake (unpaid, pending, rejected, active, completed,
       certified, needs-improvement, PDC-only after TDC, etc.). */
    private function scenario(
        Student $student,
        int $i,
        Carbon $joinedAt,
        Course $tdcFaceToFace,
        Course $tdcOnline,
        Course $pdcFourWheels,
        Course $pdcTwoWheels
    ): string {
        switch ($i % 12) {
            case 0: // Enrolled in TDC, nothing paid yet.
                $this->enroll($student, $tdcFaceToFace, 'pending', $joinedAt);
                return Student::ID_NONE;

            case 1: // TDC payment waiting for admin verification.
                $this->enroll($student, $tdcFaceToFace, 'pending', $joinedAt);
                $this->pay($student, $tdcFaceToFace, (float) $tdcFaceToFace->fee, 'pending', $joinedAt->copy()->addDay(), 'gcash');
                // Instructor gender is saved straight onto the account — no
                // admin approval request is created any more.
                if (! $student->preferred_instructor_gender) {
                    $student->update(['preferred_instructor_gender' => 'female']);
                }
                return Student::ID_PENDING;

            case 2: // Active TDC learner with an upcoming confirmed session.
                $this->enroll($student, $tdcFaceToFace, 'active', $joinedAt);
                $this->pay($student, $tdcFaceToFace, (float) $tdcFaceToFace->fee, 'verified', $joinedAt->copy()->addDays(2));
                $this->book($student, $this->upcomingTdc($i, $tdcFaceToFace), 'confirmed', $joinedAt->copy()->addDays(2));
                return Student::ID_APPROVED;

            case 3: // Completed TDC, certificate already issued.
                $tdcSession = $this->completeTdc($student, $tdcFaceToFace, $i, true);
                if ($tdcSession) {
                    $this->certify($student, $tdcFaceToFace, $tdcSession->date->copy()->addDays(2));
                }
                return Student::ID_APPROVED;

            case 4: // Completed TDC, waiting in "Ready to Issue".
                $this->completeTdc($student, $tdcFaceToFace, $i, true);
                return Student::ID_APPROVED;

            case 5: // Online TDC, active, ID still pending review.
                $this->enroll($student, $tdcOnline, 'active', $joinedAt);
                $this->pay($student, $tdcOnline, (float) $tdcOnline->fee, 'verified', $joinedAt->copy()->addDays(1));
                $this->book($student, $this->upcomingTdc($i, $tdcOnline), 'confirmed', $joinedAt->copy()->addDays(3));
                return Student::ID_PENDING;

            case 6: // Completed TDC, now enrolled in PDC but not yet paid.
                $this->completeTdc($student, $tdcFaceToFace, $i, true);
                $this->enroll($student, $pdcFourWheels, 'pending', $joinedAt->copy()->addDays(30));
                return Student::ID_APPROVED;

            case 7: // Active PDC on the 50% downpayment, with a pending booking.
                $this->completeTdc($student, $tdcFaceToFace, $i, true);
                $this->enroll($student, $pdcFourWheels, 'active', $joinedAt->copy()->addDays(30));
                $this->pay($student, $pdcFourWheels, (float) $pdcFourWheels->downpayment, 'verified', $joinedAt->copy()->addDays(31));
                $this->book($student, $this->upcomingPdc($i), 'pending', now()->subDays(1));
                return Student::ID_APPROVED;

            case 8: // Fully completed PDC with eight sessions and a certificate.
                $this->completeTdc($student, $tdcFaceToFace, $i, true);
                $this->enroll($student, $pdcFourWheels, 'completed', $joinedAt->copy()->addDays(45));
                $this->pay($student, $pdcFourWheels, (float) $pdcFourWheels->fee, 'verified', $joinedAt->copy()->addDays(46));
                $last = $this->completePdcSessions($student, $pdcFourWheels, 8, $i, 'average');
                if ($last) {
                    $this->certify($student, $pdcFourWheels, $last->date->copy()->addDays(2));
                }
                return Student::ID_APPROVED;

            case 9: // PDC in progress but flagged "needs improvement" after a safety issue.
                $this->completeTdc($student, $tdcFaceToFace, $i, true);
                $this->enroll($student, $pdcFourWheels, 'active', $joinedAt->copy()->addDays(30));
                $this->pay($student, $pdcFourWheels, (float) $pdcFourWheels->downpayment, 'verified', $joinedAt->copy()->addDays(31));
                $this->completePdcSessions($student, $pdcFourWheels, 3, $i, 'needs', true);
                return Student::ID_REJECTED;

            case 10: // Rejected online payment — the "please pay again" state.
                $this->enroll($student, $tdcOnline, 'pending', $joinedAt);
                $this->pay($student, $tdcOnline, (float) $tdcOnline->fee, 'rejected', $joinedAt->copy()->addDays(1), 'bdo');
                return Student::ID_NONE;

            default: // 11 — TDC active + PDC waiting on payment, plus a reschedule request.
                $this->completeTdc($student, $tdcFaceToFace, $i, true);
                $this->enroll($student, $pdcTwoWheels, 'pending', $joinedAt->copy()->addDays(32));
                $this->rescheduleRequest($student, $i);
                return Student::ID_PENDING;
        }
    }

    /* ------------------------------------------------------------------ */
    /* Course journeys                                                     */
    /* ------------------------------------------------------------------ */

    private function completeTdc(Student $student, Course $course, int $i, bool $passed): ?Schedule
    {
        $enrolledAt = now()->subDays(60 + ($i % 90));
        $this->enroll($student, $course, 'completed', $enrolledAt);
        $this->pay($student, $course, (float) $course->fee, 'verified', $enrolledAt->copy()->addDay());

        $schedule = $this->nextTdcPast();
        if (! $schedule) {
            return null;
        }

        $this->book($student, $schedule, 'confirmed', $enrolledAt->copy()->addDays(3));
        $this->attend($student, $schedule, $i % 11 === 0 ? 'late' : 'present');

        $exam = $passed
            ? 78 + ($i % 18)              // 78–95
            : 62 + ($i % 12);             // 62–73

        $this->assessTdc($student, $schedule, $exam);

        return $schedule;
    }

    /* Books N completed PDC sessions on distinct past schedules and records one
       final driving assessment. Returns the last schedule used. */
    private function completePdcSessions(
        Student $student,
        Course $course,
        int $count,
        int $i,
        string $classification,
        bool $safetyFlag = false
    ): ?Schedule {
        $last = null;
        $bookedAt = now()->subDays(40 + ($i % 40));

        for ($n = 0; $n < $count; $n++) {
            $schedule = $this->nextPdcPast();
            if (! $schedule) {
                break;
            }

            $this->book($student, $schedule, 'confirmed', $bookedAt->copy()->addDays($n));
            $this->attend($student, $schedule, $n === 2 && $i % 4 === 0 ? 'absent' : 'present');
            $last = $schedule;
        }

        if ($last) {
            $this->assessPdc($student, $last, $classification, $safetyFlag);
        }

        return $last;
    }

    /* ------------------------------------------------------------------ */
    /* Schedule pool                                                       */
    /* ------------------------------------------------------------------ */

    private function seedSchedules(
        Course $tdcFaceToFace,
        Course $tdcOnline,
        Course $pdcFourWheels,
        Course $pdcTwoWheels,
        $instructors
    ): void {
        $inst = fn (int $n) => $instructors[$n % max(1, $instructors->count())];

        // Past TDC sessions (12) — used for completions and attendance.
        for ($m = 1; $m <= 6; $m++) {
            for ($n = 0; $n < 2; $n++) {
                $this->tdcPast[] = $this->makeSchedule(
                    $n % 2 === 0 ? $tdcFaceToFace : $tdcOnline,
                    $inst($m + $n),
                    $this->weekday(now()->subMonths($m)->startOfMonth()->addDays(5 + $n * 9)),
                    $n === 0 ? '08:00' : '13:00',
                    $n === 0 ? '10:00' : '15:00',
                    10,
                    'done'
                );
            }
        }

        // Past PDC sessions (160) — each holds one student, so completed PDC
        // learners (8 sessions each) never share a slot.
        for ($m = 1; $m <= 8; $m++) {
            for ($n = 0; $n < 20; $n++) {
                $this->pdcPast[] = $this->makeSchedule(
                    $n % 4 === 3 ? $pdcTwoWheels : $pdcFourWheels,
                    $inst($m + $n),
                    $this->weekday(now()->subMonths($m)->startOfMonth()->addDays(2 + $n)),
                    '09:00',
                    '11:00',
                    1,
                    'done'
                );
            }
        }

        // A PDC session today so "Sessions Today" is never zero. TDC blocks
        // start on Mon/Wed/Fri only, so they are seeded separately below.
        $this->makeSchedule($pdcFourWheels, $inst(2), now()->startOfDay(), '13:00', '15:00', 1, 'open');

        // Upcoming TDC blocks for the next four weeks: Mon–Tue, Wed–Thu,
        // Fri–Sat. This mirrors the rolling generator used in production.
        $monday = now()->copy()->startOfWeek(Carbon::MONDAY);
        $block = 0;
        for ($w = 0; $w < 4; $w++) {
            foreach ([0, 2, 4] as $offset) {
                $start = $monday->copy()->addWeeks($w)->addDays($offset);
                if ($start->lt(now()->startOfDay())) {
                    continue;
                }
                $this->tdcUpcoming[] = $this->makeSchedule(
                    $block % 3 === 0 ? $tdcOnline : $tdcFaceToFace,
                    $inst($block),
                    $start,
                    '08:00',
                    '12:00',
                    20,
                    'open',
                    $start->copy()->addDay()->toDateString()
                );
                $block++;
            }
        }

        // Upcoming PDC sessions (16).
        for ($n = 0; $n < 16; $n++) {
            $this->pdcUpcoming[] = $this->makeSchedule(
                $n % 4 === 3 ? $pdcTwoWheels : $pdcFourWheels,
                $inst($n + 1),
                $this->weekday(now()->addDays(3 + $n)),
                $n % 2 === 0 ? '09:00' : '14:00',
                $n % 2 === 0 ? '11:00' : '16:00',
                1,
                'open'
            );
        }
    }

    private function makeSchedule(
        Course $course,
        Instructor $instructor,
        Carbon $date,
        string $start,
        string $end,
        int $capacity,
        string $status,
        ?string $endDate = null
    ): Schedule {
        return Schedule::create([
            'course_id' => $course->id,
            'instructor_id' => $instructor->id,
            'date' => $date->format('Y-m-d'),
            'end_date' => $endDate,
            'time_start' => $start,
            'time_end' => $end,
            'venue' => $course->type === 'PDC'
                ? 'RC Driving Academy — Driving Range'
                : 'RC Driving Academy — Main Branch',
            'max_students' => $capacity,
            'status' => $status,
        ]);
    }

    private function nextTdcPast(): ?Schedule
    {
        if (! $this->tdcPast) {
            return null;
        }

        return $this->tdcPast[$this->tdcPastCursor++ % count($this->tdcPast)];
    }

    private function nextPdcPast(): ?Schedule
    {
        if (! $this->pdcPast) {
            return null;
        }

        return $this->pdcPast[$this->pdcPastCursor++ % count($this->pdcPast)];
    }

    private function upcomingTdc(int $i, ?Course $course = null): ?Schedule
    {
        $pool = $this->tdcUpcoming;

        if ($course) {
            $matching = array_values(array_filter(
                $pool,
                fn (Schedule $s) => (int) $s->course_id === (int) $course->id
            ));
            if ($matching) {
                $pool = $matching;
            }
        }

        return $pool ? $pool[$i % count($pool)] : null;
    }

    private function upcomingPdc(int $i): ?Schedule
    {
        return $this->pdcUpcoming ? $this->pdcUpcoming[$i % count($this->pdcUpcoming)] : null;
    }

    /* ------------------------------------------------------------------ */
    /* Records                                                             */
    /* ------------------------------------------------------------------ */

    private function enroll(Student $student, Course $course, string $status, Carbon $at): Enrollment
    {
        return Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => $status,
            'enrolled_at' => $this->past($at),
        ]);
    }

    private function pay(Student $student, Course $course, float $amount, string $status, Carbon $at, string $method = 'gcash'): Payment
    {
        $at = $this->past($at);

        return Payment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'amount' => $amount,
            'method' => $method,
            'reference_no' => strtoupper($method) . '-' . str_pad((string) $student->id, 5, '0', STR_PAD_LEFT) . '-' . str_pad((string) ($student->id * 37 % 9999), 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'verified_by' => $status === 'verified' ? $this->adminId : null,
            'verified_at' => $status === 'verified' ? $at : null,
        ]);
    }

    private function book(Student $student, ?Schedule $schedule, string $status, Carbon $at): ?StudentSchedule
    {
        if (! $schedule) {
            return null;
        }

        return StudentSchedule::firstOrCreate(
            ['student_id' => $student->id, 'schedule_id' => $schedule->id],
            ['booked_at' => $at, 'status' => $status]
        );
    }

    private function attend(Student $student, Schedule $schedule, string $status): void
    {
        Attendance::firstOrCreate(
            ['schedule_id' => $schedule->id, 'student_id' => $student->id],
            [
                'status' => $status,
                'notes' => $status === 'late' ? 'Arrived 15 minutes late.' : null,
                'recorded_at' => $schedule->date->copy()->setTime(10, 0),
            ]
        );
    }

    private function assessTdc(Student $student, Schedule $schedule, int $exam): void
    {
        $passed = $exam >= 75;
        $classification = $exam >= 85 ? 'fast' : ($passed ? 'average' : 'needs');

        $feedback = Feedback::create([
            'instructor_id' => $schedule->instructor_id,
            'student_id' => $student->id,
            'schedule_id' => $schedule->id,
            'raw_text' => "Theoretical driving course exam completed with a score of {$exam}/100.",
        ]);

        Assessment::create([
            'feedback_id' => $feedback->id,
            'classification' => $classification,
            'confidence_score' => 0.9,
            'exam_score' => $exam,
            'score' => $exam,
            'passed' => $passed,
            'recommendation' => $passed
                ? 'Passed the TDC exam. Eligible to proceed to practical driving.'
                : 'Did not reach the 75 passing mark. Please review the modules and retake the exam.',
            'override_applied' => false,
            'suggestions' => null,
        ]);
    }

    private function assessPdc(Student $student, Schedule $schedule, string $classification, bool $safetyFlag): void
    {
        $stars = match ($classification) {
            'fast' => ['steering' => 5, 'brake' => 5, 'mirror' => 5],
            'needs' => ['steering' => 2, 'brake' => 2, 'mirror' => 3],
            default => ['steering' => 4, 'brake' => 3, 'mirror' => 4],
        };

        $average = array_sum($stars) / count($stars);
        $score = (int) round($average * 20);
        $built = AssessmentSuggestion::build($stars);

        $feedback = Feedback::create([
            'instructor_id' => $schedule->instructor_id,
            'student_id' => $student->id,
            'schedule_id' => $schedule->id,
            'raw_text' => $safetyFlag
                ? 'Muntik na kaming bumangga sa intersection. The student needs more practice with hazard awareness, braking distance and side-mirror checks.'
                : 'Practical driving session completed. Steering and side-mirror observation are developing steadily; braking still needs practice.',
            'star_steering' => $stars['steering'],
            'star_brake' => $stars['brake'],
            'star_mirror' => $stars['mirror'],
        ]);

        Assessment::create([
            'feedback_id' => $feedback->id,
            'classification' => $safetyFlag ? 'needs' : $classification,
            'confidence_score' => 0.8,
            'exam_score' => null,
            'score' => $score,
            'passed' => $score >= 75 && ! $safetyFlag,
            'recommendation' => $safetyFlag
                ? 'Safety review required before certification.'
                : 'Continue practising braking control and side-mirror checks.',
            'override_applied' => $safetyFlag,
            'suggestions' => $built['suggestions'],
        ]);
    }

    private function certify(Student $student, Course $course, Carbon $at): void
    {
        $year = (int) $at->format('Y');

        if (! isset($this->certSeq[$year])) {
            $this->certSeq[$year] = Certification::whereYear('issued_at', $year)->count();
        }

        $this->certSeq[$year]++;

        Certification::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'issued_by' => $this->adminId,
            'issued_at' => $at,
            'certificate_no' => 'RC-' . $year . '-' . str_pad((string) $this->certSeq[$year], 4, '0', STR_PAD_LEFT),
            'status' => 'active',
        ]);
    }

    private function rescheduleRequest(Student $student, int $i): void
    {
        $from = $this->upcomingPdc($i);
        $to = $this->pdcUpcoming[($i + 1) % count($this->pdcUpcoming)] ?? null;

        if (! $from || ! $to || $from->id === $to->id) {
            return;
        }

        Request::create([
            'type' => Request::TYPE_RESCHEDULE,
            'student_id' => $student->id,
            'from_schedule_id' => $from->id,
            'schedule_id' => $to->id,
            'note' => 'I have a conflict on the original date. May I move to this session instead?',
            'status' => Request::STATUS_PENDING,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Valid ID demo documents                                             */
    /* ------------------------------------------------------------------ */

    private function attachId(Student $student, string $status, int $i): void
    {
        if ($status === Student::ID_NONE) {
            return;
        }

        $type = array_keys(self::ID_TYPES)[$i % count(self::ID_TYPES)];
        $label = self::ID_TYPES[$type];

        $path = 'valid-ids/' . $student->id . '/' . Str::uuid() . '.pdf';
        Storage::disk('local')->put($path, $this->makeIdPdf($student->user->name, $label, $status));

        $student->update([
            'document_path' => $path,
            'id_document_type' => $type,
            'id_document_name' => 'valid-id-sample.pdf',
            'id_document_mime' => 'application/pdf',
            'id_status' => $status,
            'id_submitted_at' => now()->subDays(2 + ($i % 25)),
            'id_reviewed_at' => $status === Student::ID_PENDING ? null : now()->subDay(),
            'id_reviewed_by' => $status === Student::ID_PENDING ? null : $this->adminId,
            'id_rejection_reason' => $status === Student::ID_REJECTED
                ? self::REJECT_REASONS[$i % count(self::REJECT_REASONS)]
                : null,
        ]);
    }

    /* Builds a tiny, valid one-page PDF with the sample ID details. Kept
       dependency-free (no GD/dompdf needed) so seeding works anywhere. */
    private function makeIdPdf(string $name, string $typeLabel, string $status): string
    {
        $lines = [
            'RC DRIVING ACADEMY',
            'SAMPLE VALID ID - DEMO DATA ONLY',
            'Name: ' . $name,
            'ID type: ' . $typeLabel,
            'Review status: ' . ucfirst($status),
            'This document contains no real personal information.',
        ];

        $stream = '';
        $y = 205;

        foreach ($lines as $index => $line) {
            $size = $index === 0 ? 16 : 11;
            $stream .= sprintf(
                "BT /F1 %d Tf 40 %d Td (%s) Tj ET\n",
                $size,
                $y,
                $this->escapePdfText($line)
            );
            $y -= $index === 0 ? 28 : 20;
        }

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 400 260] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            4 => "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . 'endstream',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= 'trailer' . "\n" . '<< /Size ' . (count($objects) + 1) . ' /Root 1 0 R >>' . "\n";
        $pdf .= 'startxref' . "\n" . $xref . "\n%%EOF\n";

        return $pdf;
    }

    private function escapePdfText(string $text): string
    {
        $text = preg_replace('/[^\x20-\x7E]/', '?', $text);

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private function course(string $type, string $format): ?Course
    {
        return Course::where('type', $type)
            ->where('format', $format)
            ->where('is_active', true)
            ->first()
            ?? Course::where('type', $type)->where('is_active', true)->first();
    }

    private function ensureInstructors(): void
    {
        $seeds = [
            ['name' => 'Rosa Lim', 'email' => 'dummy.instructor1@rcdriving.ph', 'gender' => 'female', 'specialization' => 'TDC+PDC', 'license_no' => 'LIC-200001'],
            ['name' => 'Tony Dela Cruz', 'email' => 'dummy.instructor2@rcdriving.ph', 'gender' => 'male', 'specialization' => 'PDC', 'license_no' => 'LIC-200002'],
            ['name' => 'Beth Garcia', 'email' => 'dummy.instructor3@rcdriving.ph', 'gender' => 'female', 'specialization' => 'TDC', 'license_no' => 'LIC-200003'],
            ['name' => 'Rene Bautista', 'email' => 'dummy.instructor4@rcdriving.ph', 'gender' => 'male', 'specialization' => 'PDC', 'license_no' => 'LIC-200004'],
        ];

        foreach ($seeds as $seed) {
            if (Instructor::count() >= 5) {
                break;
            }

            $user = User::firstOrCreate(
                ['email' => $seed['email']],
                [
                    'name' => $seed['name'],
                    'password' => Hash::make('instructor123'),
                    'role' => 'instructor',
                    'is_active' => true,
                ]
            );

            Instructor::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'phone' => '09' . str_pad((string) random_int(170000000, 179999999), 9, '0', STR_PAD_LEFT),
                    'specialization' => $seed['specialization'],
                    'license_no' => $seed['license_no'],
                    'gender' => $seed['gender'],
                ]
            );
        }
    }

    private function weekday(Carbon $date): Carbon
    {
        return $date->dayOfWeek === Carbon::SUNDAY ? $date->copy()->addDay() : $date->copy();
    }

    /* Never write an "in the past" timestamp in the future. */
    private function past(Carbon $date): Carbon
    {
        return $date->isFuture() ? now()->subDay() : $date->copy();
    }
}
