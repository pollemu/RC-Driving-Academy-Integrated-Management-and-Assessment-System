<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Instructor;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
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

        // Real instructor accounts (role = instructor).
        $instructorSeeds = [
            ['name' => 'Mario Santos', 'email' => 'mario.santos@rcdriving.ph', 'phone' => '09171234567', 'specialization' => 'TDC', 'license_no' => 'LIC-100001'],
            ['name' => 'Elena Reyes', 'email' => 'elena.reyes@rcdriving.ph', 'phone' => '09179876543', 'specialization' => 'PDC', 'license_no' => 'LIC-100002'],
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
            ]));
        }

        // Real student accounts (role = student).
        $studentSeeds = [
            ['name' => 'John Cruz', 'email' => 'john.cruz@rcdriving.ph', 'phone' => '09171230001', 'address' => 'Quezon City', 'birthdate' => '2002-05-12', 'license_type' => 'student-permit'],
            ['name' => 'Anna Lim', 'email' => 'anna.lim@rcdriving.ph', 'phone' => '09171230002', 'address' => 'Makati City', 'birthdate' => '2001-09-30', 'license_type' => 'non-pro'],
            ['name' => 'Paolo Garcia', 'email' => 'paolo.garcia@rcdriving.ph', 'phone' => '09171230003', 'address' => 'Pasig City', 'birthdate' => '2003-01-18', 'license_type' => 'student-permit'],
            ['name' => 'Maria Torres', 'email' => 'maria.torres@rcdriving.ph', 'phone' => '09171230004', 'address' => 'Taguig City', 'birthdate' => '2000-11-05', 'license_type' => 'pro'],
        ];

        $students = collect();
        foreach ($studentSeeds as $i => $seed) {
            $user = User::create([
                'name' => $seed['name'],
                'email' => $seed['email'],
                'password' => Hash::make('student123'),
                'role' => 'student',
                'is_active' => true,
            ]);
            $students->push(Student::create([
                'user_id' => $user->id,
                'instructor_id' => $instructors->get($i % $instructors->count())->id,
                'phone' => $seed['phone'],
                'address' => $seed['address'],
                'birthdate' => $seed['birthdate'],
                'license_type' => $seed['license_type'],
                'document_path' => null,
            ]));
        }

        // A couple of upcoming sessions per instructor so the dashboard is not empty.
        $courses = Course::where('is_active', true)->get();
        foreach ($instructors as $index => $instructor) {
            $course = $courses->where('type', $instructor->specialization === 'PDC' ? 'PDC' : 'TDC')->first()
                ?? $courses->first();
            Schedule::create([
                'course_id' => $course->id,
                'instructor_id' => $instructor->id,
                'date' => now()->addDays(7 + $index * 2)->format('Y-m-d'),
                'time_start' => '08:00',
                'time_end' => '10:00',
                'venue' => 'RC Driving Academy — Main Branch',
                'max_students' => 5,
                'status' => 'open',
            ]);
        }
    }
}
