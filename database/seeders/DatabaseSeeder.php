<?php

namespace Database\Seeders;

use App\Models\Course;
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
        // Admin account — the only fixed super-user.
        User::updateOrCreate(
            ['email' => 'admin@rcdriving.ph'],
            [
                'name' => 'RC Admin',
                'email' => 'admin@rcdriving.ph',
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

        // Real instructor accounts (one TDC instructor, one PDC instructor).
        $instructorSeeds = [
            ['name' => 'Mario Santos', 'email' => 'mario.santos@rcdriving.ph', 'phone' => '09171234567', 'specialization' => 'TDC', 'license_no' => 'LIC-100001', 'gender' => 'male'],
            ['name' => 'Elena Reyes', 'email' => 'elena.reyes@rcdriving.ph', 'phone' => '09179876543', 'specialization' => 'PDC', 'license_no' => 'LIC-100002', 'gender' => 'female'],
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

        $tdcInstructor = $instructors->firstWhere('specialization', 'TDC');
        $pdcInstructor = $instructors->firstWhere('specialization', 'PDC');

        // Real student accounts (role = student).
        $studentSeeds = [
            ['name' => 'John Cruz', 'email' => 'john.cruz@rcdriving.ph', 'phone' => '09171230001', 'address' => 'Quezon City', 'birthdate' => '2002-05-12', 'license_type' => 'student-permit', 'course' => 'TDC', 'instructor' => 'TDC', 'gender' => 'male', 'pref' => 'female'],
            ['name' => 'Anna Lim', 'email' => 'anna.lim@rcdriving.ph', 'phone' => '09171230002', 'address' => 'Makati City', 'birthdate' => '2001-09-30', 'license_type' => 'non-pro', 'course' => 'TDC', 'instructor' => 'TDC', 'gender' => 'female', 'pref' => 'male'],
            ['name' => 'Paolo Garcia', 'email' => 'paolo.garcia@rcdriving.ph', 'phone' => '09171230003', 'address' => 'Pasig City', 'birthdate' => '2003-01-18', 'license_type' => 'student-permit', 'course' => 'PDC', 'instructor' => 'PDC', 'gender' => 'male', 'pref' => null],
            ['name' => 'Maria Torres', 'email' => 'maria.torres@rcdriving.ph', 'phone' => '09171230004', 'address' => 'Taguig City', 'birthdate' => '2000-11-05', 'license_type' => 'pro', 'course' => 'PDC', 'instructor' => 'PDC', 'gender' => 'female', 'pref' => 'female'],
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

        // Sessions: TDC sessions hold up to 10 students; PDC sessions hold 1 student.
        $tdcSessions = [
            ['date' => $nextWeekday(now()->addDays(7)), 'time_start' => '08:00', 'time_end' => '10:00', 'venue' => 'RC Driving Academy — Main Branch'],
            ['date' => $nextWeekday(now()->addDays(14)), 'time_start' => '13:00', 'time_end' => '15:00', 'venue' => 'RC Driving Academy — Main Branch'],
        ];
        foreach ($tdcSessions as $s) {
            Schedule::create([
                'course_id' => $tdcCourse->id,
                'instructor_id' => $tdcInstructor->id,
                'date' => $s['date']->format('Y-m-d'),
                'time_start' => $s['time_start'],
                'time_end' => $s['time_end'],
                'venue' => $s['venue'],
                'max_students' => 10,
                'status' => 'open',
            ]);
        }

        $pdcSessions = [
            ['date' => $nextWeekday(now()->addDays(9)), 'time_start' => '09:00', 'time_end' => '11:00', 'venue' => 'RC Driving Academy — Driving Range'],
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

        // Confirmed bookings: each TDC student booked into the first TDC session,
        // each PDC student into their own PDC session.
        $firstTdc = Schedule::where('course_id', $tdcCourse->id)->orderBy('date')->first();
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

        // One PENDING booking to showcase the admin "accept the instructor" workflow.
        $secondTdc = Schedule::where('course_id', $tdcCourse->id)->orderBy('date')->skip(1)->first();
        if ($secondTdc) {
            $pendingStudent = $students->where('instructor_id', $tdcInstructor->id)->first();
            if ($pendingStudent) {
                StudentSchedule::firstOrCreate(
                    ['student_id' => $pendingStudent->id, 'schedule_id' => $secondTdc->id],
                    ['booked_at' => now()->subDays(1), 'status' => 'pending']
                );
            }
        }
    }
}
