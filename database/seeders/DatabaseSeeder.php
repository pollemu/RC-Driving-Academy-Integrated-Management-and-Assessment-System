<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
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

        $courses = [
            ['name' => 'TDC Face to Face', 'type' => 'TDC', 'format' => 'Face to Face', 'fee' => 799.00],
            ['name' => 'TDC Online', 'type' => 'TDC', 'format' => 'Online', 'fee' => 999.00],
            ['name' => 'PDC 4 Wheels Down Payment', 'type' => 'PDC', 'format' => '4 Wheels', 'fee' => 2000.00],
            ['name' => 'PDC 4 Wheels Full Payment', 'type' => 'PDC', 'format' => '4 Wheels', 'fee' => 4000.00],
            ['name' => 'PDC 2 Wheels', 'type' => 'PDC', 'format' => '2 Wheels', 'fee' => 1499.00],
        ];

        foreach ($courses as $course) {
            Course::updateOrCreate(['name' => $course['name']], array_merge($course, ['is_active' => true]));
        }
    }
}
