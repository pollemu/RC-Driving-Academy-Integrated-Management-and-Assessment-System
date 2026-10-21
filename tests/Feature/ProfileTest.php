<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_profile(): void
    {
        $this->getJson('/api/profile')->assertStatus(401);
    }

    public function test_student_can_view_and_update_profile(): void
    {
        $user = User::factory()->student()->create(['name' => 'Old Name']);
        Student::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('user.name', 'Old Name')
            ->assertJsonPath('user.role', 'student');

        $this->actingAs($user)->putJson('/api/profile', [
            'name' => 'New Name',
            'email' => 'new.student@example.com',
            'phone' => '09171234567',
            'address' => 'Lipa City',
            'gender' => 'male',
            'license_type' => 'non-pro',
        ])
            ->assertOk()
            ->assertJsonPath('user.name', 'New Name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name', 'email' => 'new.student@example.com']);
        $this->assertDatabaseHas('students', ['user_id' => $user->id, 'phone' => '09171234567', 'gender' => 'male']);
    }

    public function test_profile_email_must_be_unique(): void
    {
        $user = User::factory()->student()->create();
        Student::factory()->create(['user_id' => $user->id]);
        $other = User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($user)->putJson('/api/profile', [
            'name' => 'X',
            'email' => 'taken@example.com',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpassword')]);

        $this->actingAs($user)->postJson('/api/profile/password', [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('oldpassword', $user->fresh()->password));
    }

    public function test_password_change_succeeds_with_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpassword')]);

        $this->actingAs($user)->postJson('/api/profile/password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_forgot_password_reports_channel_unavailable(): void
    {
        $this->postJson('/api/password/forgot', ['email' => 'someone@example.com'])
            ->assertStatus(503);
    }
}
