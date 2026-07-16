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
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentSchedule;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private int $certCounter = 0;

    public function run(): void
    {
        // Admin account
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

        // Courses
        // downpayment = amount required to activate scheduling (TDC & PDC 2W = full;
        // PDC 4 Wheels = 50% downpayment, balance payable later).
        $coursesData = [
            ['name' => 'TDC Face to Face', 'type' => 'TDC', 'format' => 'Face to Face', 'fee' => 799.00, 'downpayment' => 799.00],
            ['name' => 'TDC Online', 'type' => 'TDC', 'format' => 'Online', 'fee' => 999.00, 'downpayment' => 999.00],
            ['name' => 'PDC 4 Wheels', 'type' => 'PDC', 'format' => '4 Wheels', 'fee' => 4000.00, 'downpayment' => 2000.00],
            ['name' => 'PDC 2 Wheels', 'type' => 'PDC', 'format' => '2 Wheels', 'fee' => 1499.00, 'downpayment' => 1499.00],
        ];
        // Deactivate the old split PDC rows if they still exist from a prior seed.
        Course::whereIn('name', ['PDC 4 Wheels Down Payment', 'PDC 4 Wheels Full Payment'])
            ->update(['is_active' => false]);
        foreach ($coursesData as $course) {
            Course::updateOrCreate(['name' => $course['name']], array_merge($course, ['is_active' => true]));
        }
        $courses = Course::where('is_active', true)->get();

        // Idempotency guard — skip the demo data if students already exist.
        if (Student::count() > 0) {
            return;
        }

        $admin = User::where('email', 'admin@rcdriving.ph')->first();

        // Instructors
        $instructors = collect();
        $specs = ['TDC', 'PDC', 'TDC+PDC'];
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->instructor()->create([
                'name' => fake()->name(),
                'email' => "instructor{$i}@rcda.test",
            ]);
            $instructors->push(Instructor::create([
                'user_id' => $user->id,
                'phone' => fake()->phoneNumber(),
                'specialization' => $specs[$i],
                'license_no' => 'LIC-' . Str::random(6),
            ]));
        }

        // Students
        $students = collect();
        for ($i = 0; $i < 12; $i++) {
            $user = User::factory()->student()->create([
                'name' => fake()->name(),
                'email' => "student{$i}@rcda.test",
            ]);
            $students->push(Student::create([
                'user_id' => $user->id,
                'phone' => fake()->phoneNumber(),
                'address' => fake()->address(),
                'birthdate' => fake()->dateTimeBetween('-40 years', '-18 years')->format('Y-m-d'),
                'license_type' => fake()->randomElement(['non-pro', 'pro', 'student-permit']),
                'document_path' => null,
            ]));
        }

        // Schedules — two per course
        $schedules = collect();
        foreach ($courses as $course) {
            for ($j = 0; $j < 2; $j++) {
                $instr = $instructors->random();
                $schedules->push(Schedule::create([
                    'course_id' => $course->id,
                    'instructor_id' => $instr->id,
                    'date' => now()->addDays(rand(1, 30))->format('Y-m-d'),
                    'time_start' => '08:00',
                    'time_end' => '10:00:00',
                    'venue' => fake()->randomElement(['Room 1', 'Room 2', 'Driving Range A', 'Driving Range B']),
                    'max_students' => rand(3, 8),
                    'status' => 'open',
                ]));
            }
        }

        // Enrollments + bookings + payments + feedback/assessments + certifications
        foreach ($students as $stu) {
            foreach ($courses->random(rand(1, 2)) as $course) {
                $status = fake()->randomElement(['active', 'active', 'pending', 'completed']);

                Enrollment::create([
                    'student_id' => $stu->id,
                    'course_id' => $course->id,
                    'status' => $status,
                    'enrolled_at' => now()->subDays(rand(5, 60)),
                ]);

                Payment::create([
                    'student_id' => $stu->id,
                    'course_id' => $course->id,
                    'amount' => $course->fee,
                    'method' => fake()->randomElement(['gcash', 'bdo']),
                    'reference_no' => 'SEED-' . Str::random(8),
                    'status' => $status === 'pending' ? 'pending' : 'verified',
                    'verified_by' => $status === 'pending' ? null : $admin->id,
                    'verified_at' => $status === 'pending' ? null : now(),
                ]);

                if (in_array($status, ['active', 'completed'])) {
                    $sch = $schedules->where('course_id', $course->id)->first();
                    if ($sch) {
                        StudentSchedule::firstOrCreate(
                            ['student_id' => $stu->id, 'schedule_id' => $sch->id],
                            ['booked_at' => now()->subDays(rand(1, 20))]
                        );

                        Attendance::firstOrCreate(
                            ['schedule_id' => $sch->id, 'student_id' => $stu->id],
                            [
                                'status' => fake()->randomElement(['present', 'present', 'late', 'absent']),
                                'notes' => null,
                                'recorded_at' => now()->subDays(rand(1, 20)),
                            ]
                        );

                        if ($course->type === 'PDC') {
                            $fb = Feedback::firstOrCreate(
                                [
                                    'instructor_id' => $sch->instructor_id,
                                    'student_id' => $stu->id,
                                    'schedule_id' => $sch->id,
                                ],
                                [
                                    'raw_text' => fake()->paragraph(),
                                    'star_steering' => rand(3, 5),
                                    'star_speed' => rand(3, 5),
                                    'star_lane' => rand(3, 5),
                                    'star_parking' => rand(3, 5),
                                    'star_safety' => rand(3, 5),
                                    'star_mirror' => rand(3, 5),
                                ]
                            );
                            if ($fb->wasRecentlyCreated) {
                                Assessment::create([
                                    'feedback_id' => $fb->id,
                                    'classification' => fake()->randomElement(['fast', 'average', 'needs']),
                                    'confidence_score' => fake()->randomFloat(2, 0.6, 0.98),
                                    'recommendation' => fake()->sentence(),
                                    'override_applied' => false,
                                ]);
                            }
                        }
                    }
                }

                if ($status === 'completed') {
                    Certification::firstOrCreate(
                        ['student_id' => $stu->id, 'course_id' => $course->id],
                        [
                            'issued_by' => $admin->id,
                            'issued_at' => now()->subDays(rand(1, 10)),
                            'certificate_no' => $this->certNo(),
                            'status' => 'active',
                        ]
                    );
                }
            }
        }
    }

    private function certNo(): string
    {
        $this->certCounter++;

        return 'CERT-' . str_pad((string) $this->certCounter, 4, '0', STR_PAD_LEFT) . '-' . Str::random(4);
    }
}
