<?php

namespace Database\Seeders;

use App\Models\AdminNotification;
use App\Models\Student;
use Illuminate\Database\Seeder;

/* Demo admin feed entries so every notification tab has something to show on
   a fresh install. Safe to re-run: it does nothing once notifications exist. */
class AdminNotificationSeeder extends Seeder
{
    public function run(): void
    {
        if (AdminNotification::count() > 0) {
            return;
        }

        $students = Student::with('user')->orderBy('id')->limit(6)->get()->values();
        $student = fn (int $i) => $students->count() ? $students->get($i % $students->count()) : null;
        $name = fn (int $i, string $fallback = 'Demo Student') => $student($i)?->user?->name ?? $fallback;
        $userId = fn (int $i) => $student($i)?->user_id;
        $studentId = fn (int $i) => $student($i)?->id;

        $samples = [
            [
                'category' => AdminNotification::CATEGORY_PAYMENT,
                'title' => 'Payment verified',
                'body' => $name(0) . ' paid ₱2,000.00 for PDC 4 Wheels.',
                'meta' => ['student_id' => $studentId(0), 'panel' => 'payments'],
                'user_id' => $userId(0),
                'minutes_ago' => 8,
                'read' => false,
            ],
            [
                'category' => AdminNotification::CATEGORY_ACCOUNT,
                'title' => 'New student account',
                'body' => $name(1) . ' just created a student account.',
                'meta' => ['student_id' => $studentId(1), 'panel' => 'students'],
                'user_id' => $userId(1),
                'minutes_ago' => 22,
                'read' => false,
            ],
            [
                'category' => AdminNotification::CATEGORY_REQUEST,
                'title' => 'New PDC booking request',
                'body' => $name(2) . ' requested a PDC lesson on Nov 4, 2026 with Tony Dela Cruz.',
                'meta' => ['student_id' => $studentId(2), 'panel' => 'requests'],
                'user_id' => $userId(2),
                'minutes_ago' => 45,
                'read' => false,
            ],
            [
                'category' => AdminNotification::CATEGORY_CHAT,
                'title' => 'Student wants to talk to a human',
                'body' => $name(3) . ': “Can I move my session to next week?”',
                'meta' => ['student_id' => $studentId(3)],
                'user_id' => $userId(3),
                'minutes_ago' => 70,
                'read' => false,
            ],
            [
                'category' => AdminNotification::CATEGORY_REQUEST,
                'title' => 'Reschedule request',
                'body' => $name(4) . ' wants to move a session from Oct 29, 2026 to Nov 3, 2026.',
                'meta' => ['student_id' => $studentId(4), 'panel' => 'requests'],
                'user_id' => $userId(4),
                'minutes_ago' => 130,
                'read' => false,
            ],
            [
                'category' => AdminNotification::CATEGORY_ACCOUNT,
                'title' => 'Valid ID submitted for review',
                'body' => $name(5) . ' uploaded a Philippine Passport.',
                'meta' => ['student_id' => $studentId(5), 'panel' => 'idverification'],
                'user_id' => $userId(5),
                'minutes_ago' => 300,
                'read' => false,
            ],
            [
                'category' => AdminNotification::CATEGORY_CERTIFICATE,
                'title' => 'Certificate issued',
                'body' => 'RC-2026-0041 — ' . $name(0) . ' (TDC Face to Face).',
                'meta' => ['panel' => 'certifications'],
                'user_id' => $userId(0),
                'minutes_ago' => 420,
                'read' => true,
            ],
            [
                'category' => AdminNotification::CATEGORY_PAYMENT,
                'title' => 'Payment verified',
                'body' => $name(1) . ' paid ₱799.00 for TDC Face to Face.',
                'meta' => ['student_id' => $studentId(1), 'panel' => 'payments'],
                'user_id' => $userId(1),
                'minutes_ago' => 1500,
                'read' => true,
            ],
            [
                'category' => AdminNotification::CATEGORY_CHAT,
                'title' => 'Student wants to talk to a human',
                'body' => $name(2) . ': “Please call me about my payment.”',
                'meta' => ['student_id' => $studentId(2)],
                'user_id' => $userId(2),
                'minutes_ago' => 1800,
                'read' => true,
            ],
            [
                'category' => AdminNotification::CATEGORY_ACCOUNT,
                'title' => 'New student account',
                'body' => $name(3) . ' just created a student account.',
                'meta' => ['student_id' => $studentId(3), 'panel' => 'students'],
                'user_id' => $userId(3),
                'minutes_ago' => 2900,
                'read' => true,
            ],
        ];

        foreach ($samples as $sample) {
            $notification = AdminNotification::create([
                'category' => $sample['category'],
                'title' => $sample['title'],
                'body' => $sample['body'],
                'meta' => array_filter($sample['meta'], fn ($value) => $value !== null),
                'user_id' => $sample['user_id'],
                'read_at' => $sample['read'] ? now()->subMinutes($sample['minutes_ago'] - 5) : null,
            ]);

            $notification->forceFill([
                'created_at' => now()->subMinutes($sample['minutes_ago']),
            ])->save();
        }
    }
}
