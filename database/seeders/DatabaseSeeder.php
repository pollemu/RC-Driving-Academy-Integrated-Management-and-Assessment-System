<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\AiTrainingExample;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Admin account — the only fixed super-user. Inserted once, never
        // overwritten, so re-seeding on deploy cannot reset a changed password.
        User::firstOrCreate(
            ['email' => 'admin@rcdriving.ph'],
            [
                'name' => 'RC Admin',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        // Courses (downpayment = amount required to activate scheduling).
        $coursesData = [
            ['name' => 'TDC Face to Face', 'type' => 'TDC', 'format' => 'Face to Face', 'fee' => 799.00, 'downpayment' => 799.00],
            ['name' => 'TDC Online', 'type' => 'TDC', 'format' => 'Online', 'fee' => 999.00, 'downpayment' => 999.00],
            ['name' => 'PDC 4 Wheels', 'type' => 'PDC', 'format' => '4 Wheels', 'fee' => 4000.00, 'downpayment' => 2000.00],
            ['name' => 'PDC 2 Wheels', 'type' => 'PDC', 'format' => '2 Wheels', 'fee' => 1499.00, 'downpayment' => 1499.00],
        ];
        Course::whereIn('name', ['PDC 4 Wheels Down Payment', 'PDC 4 Wheels Full Payment'])
            ->update(['is_active' => false]);
        foreach ($coursesData as $course) {
            Course::updateOrCreate(['name' => $course['name']], array_merge($course, ['is_active' => true]));
        }

        // Idempotency guard — never recreate accounts if they already exist.
        if (Instructor::count() > 0 || Student::count() > 0) {
            return;
        }

        $admin = User::where('email', 'admin@rcdriving.ph')->first();

        // One demo instructor who covers both course types.
        $instructorSeeds = [
            ['name' => 'Mario Santos', 'email' => 'mario.santos@rcdriving.ph', 'phone' => '09171234567', 'specialization' => 'TDC+PDC', 'license_no' => 'LIC-100001', 'gender' => 'male'],
        ];

        $instructors = collect();
        foreach ($instructorSeeds as $seed) {
            $user = User::create([
                'name' => $seed['name'],
                'email' => $seed['email'],
                'password' => Hash::make('instructor123'),
                'role' => 'instructor',
                'is_active' => true,
            ]);
            $instructors->push(Instructor::create([
                'user_id' => $user->id,
                'phone' => $seed['phone'],
                'specialization' => $seed['specialization'],
                'license_no' => $seed['license_no'],
                'gender' => $seed['gender'],
            ]));
        }

        $tdcInstructor = $instructors->first();
        $pdcInstructor = $instructors->first();

        // One demo student (TDC) so each role has exactly one seeded login.
        $studentSeeds = [
            ['name' => 'John Cruz', 'email' => 'john.cruz@rcdriving.ph', 'phone' => '09171230001', 'address' => 'Quezon City', 'birthdate' => '2002-05-12', 'license_type' => 'student-permit', 'course' => 'TDC', 'instructor' => 'TDC', 'gender' => 'male', 'pref' => 'female'],
        ];

        $tdcCourse = Course::where('type', 'TDC')->where('format', 'Face to Face')->first();
        $pdcCourse = Course::where('type', 'PDC')->where('format', '4 Wheels')->first();

        $students = collect();
        foreach ($studentSeeds as $seed) {
            $user = User::create([
                'name' => $seed['name'],
                'email' => $seed['email'],
                'password' => Hash::make('student123'),
                'role' => 'student',
                'is_active' => true,
            ]);
            $instructor = $seed['instructor'] === 'PDC' ? $pdcInstructor : $tdcInstructor;
            $student = Student::create([
                'user_id' => $user->id,
                'instructor_id' => $instructor->id,
                'phone' => $seed['phone'],
                'address' => $seed['address'],
                'birthdate' => $seed['birthdate'],
                'license_type' => $seed['license_type'],
                'gender' => $seed['gender'],
                'preferred_instructor_gender' => $seed['pref'],
                'document_path' => null,
            ]);

            // Give each student an ACTIVE enrollment + VERIFIED payment so they can book.
            $course = $seed['course'] === 'PDC' ? $pdcCourse : $tdcCourse;
            Enrollment::create([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'status' => 'active',
                'enrolled_at' => now()->subDays(10),
            ]);
            Payment::create([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'amount' => $course->fee,
                'method' => 'gcash',
                'reference_no' => 'SEED-' . strtoupper(substr(md5($user->email), 0, 8)),
                'status' => 'verified',
                'verified_by' => $admin->id,
                'verified_at' => now()->subDays(9),
            ]);

            $students->push($student);
        }

        // Academy operates Monday–Saturday only; never seed a Sunday session.
        $nextWeekday = function ($date) {
            return $date->dayOfWeek === Carbon::SUNDAY ? $date->copy()->addDay() : $date;
        };

        // TDC runs in fixed 2-day blocks (Mon–Tue, Wed–Thu, Fri–Sat). Seed a
        // week of upcoming blocks so a learner can pick the one that suits them.
        $monday = now()->copy()->startOfWeek(Carbon::MONDAY);
        $tdcBlocks = [];
        foreach ([0, 2, 4] as $offset) {
            $start = $monday->copy()->addWeek()->addDays($offset);
            $tdcBlocks[] = [
                'date' => $start,
                'end_date' => $start->copy()->addDay(),
                'time_start' => '08:00',
                'time_end' => '12:00',
                'venue' => 'RC Driving Academy — Main Branch',
            ];
        }
        foreach ($tdcBlocks as $s) {
            Schedule::create([
                'course_id' => $tdcCourse->id,
                'instructor_id' => $tdcInstructor->id,
                'date' => $s['date']->format('Y-m-d'),
                'end_date' => $s['end_date']->format('Y-m-d'),
                'time_start' => $s['time_start'],
                'time_end' => $s['time_end'],
                'venue' => $s['venue'],
                'max_students' => 20,
                'status' => 'open',
                'auto_generated' => true,
            ]);
        }

        // PDC lessons are 1-on-1 and run in the afternoon, after the TDC block
        // that ends at noon, so the single demo instructor is never double-booked.
        $pdcSessions = [
            ['date' => $nextWeekday(now()->addDays(9)), 'time_start' => '13:00', 'time_end' => '15:00', 'venue' => 'RC Driving Academy — Driving Range'],
            ['date' => $nextWeekday(now()->addDays(16)), 'time_start' => '14:00', 'time_end' => '16:00', 'venue' => 'RC Driving Academy — Driving Range'],
        ];
        foreach ($pdcSessions as $s) {
            Schedule::create([
                'course_id' => $pdcCourse->id,
                'instructor_id' => $pdcInstructor->id,
                'date' => $s['date']->format('Y-m-d'),
                'time_start' => $s['time_start'],
                'time_end' => $s['time_end'],
                'venue' => $s['venue'],
                'max_students' => 1,
                'status' => 'open',
            ]);
        }

        // A second PDC session on the first PDC day (later, non-overlapping time)
        // so the booking calendar shows another available slot.
        Schedule::create([
            'course_id' => $pdcCourse->id,
            'instructor_id' => $pdcInstructor->id,
            'date' => $pdcSessions[0]['date']->format('Y-m-d'),
            'time_start' => '15:00',
            'time_end' => '17:00',
            'venue' => 'RC Driving Academy — Driving Range',
            'max_students' => 1,
            'status' => 'open',
        ]);

        // Confirmed booking: the demo student is booked into the first session
        // for their course. TDC only needs one 2-day block.
        $firstTdc = Schedule::where('course_id', $tdcCourse->id)
            ->whereNotNull('end_date')
            ->orderBy('date')
            ->first();
        $firstPdc = Schedule::where('course_id', $pdcCourse->id)->orderBy('date')->first();

        foreach ($students as $student) {
            $course = $student->enrollments()->with('course')->first()->course;
            $schedule = $course->type === 'PDC' ? $firstPdc : $firstTdc;
            if ($schedule) {
                StudentSchedule::firstOrCreate(
                    ['student_id' => $student->id, 'schedule_id' => $schedule->id],
                    ['booked_at' => now()->subDays(3), 'status' => 'confirmed']
                );
            }
        }

        // Seed the teachable grading model with a small labeled corpus so the
        // AI can classify right from the first demo (it keeps learning from
        // every real assessment afterwards).
        $this->seedNlpExamples();
        $this->call(AdminNotificationSeeder::class);
    }

    private function seedNlpExamples(): void
    {
        AiTrainingExample::updateOrCreate(
            ['raw_text' => 'Magaling siya mag-drive. Excellent steering control at outstanding safety awareness. Accurate lane discipline at well controlled speed sa lahat ng road types.'],
            ['label' => 'fast', 'stars' => ['steering' => 5, 'speed' => 5, 'lane' => 5, 'parking' => 5, 'safety' => 5, 'mirror' => 5]]
        );

        AiTrainingExample::updateOrCreate(
            ['raw_text' => 'Student shows excellent control. Parking mastered on first attempt. Very smooth and safe all throughout the session.'],
            ['label' => 'fast', 'stars' => ['steering' => 5, 'speed' => 4, 'lane' => 5, 'parking' => 5, 'safety' => 5, 'mirror' => 4]]
        );

        AiTrainingExample::updateOrCreate(
            ['raw_text' => 'Good progress sa steering pero kailangan pa ng practice sa parking. Lane discipline is improving. Speed management adequate pero can be better.'],
            ['label' => 'average', 'stars' => ['steering' => 4, 'speed' => 3, 'lane' => 4, 'parking' => 2, 'safety' => 4, 'mirror' => 3]]
        );

        AiTrainingExample::updateOrCreate(
            ['raw_text' => 'Adequate speed management but can be better. Basic maneuvers okay, kailangan lang ng consistency sa mirror checking.'],
            ['label' => 'average', 'stars' => ['steering' => 3, 'speed' => 3, 'lane' => 3, 'parking' => 3, 'safety' => 4, 'mirror' => 3]]
        );

        AiTrainingExample::updateOrCreate(
            ['raw_text' => 'Student has difficulty with lane discipline and unsafe speed on turns. Parking was incorrect multiple times. Poor control sa manibela.'],
            ['label' => 'needs', 'stars' => ['steering' => 2, 'speed' => 2, 'lane' => 2, 'parking' => 2, 'safety' => 3, 'mirror' => 2]]
        );

        AiTrainingExample::updateOrCreate(
            ['raw_text' => 'Delikado ang bilis sa highway, unsafe speed sa overtaking. Madalas mag-overshoot sa parking at nag-struggle sa basic maneuvers.'],
            ['label' => 'needs', 'stars' => ['steering' => 2, 'speed' => 2, 'lane' => 2, 'parking' => 1, 'safety' => 2, 'mirror' => 2]]
        );
    }
}
