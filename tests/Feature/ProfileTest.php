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

    public function test_changing_email_clears_the_verified_flag(): void
    {
        $user = User::factory()->student()->create(['email_verified_at' => now()]);
        Student::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->putJson('/api/profile', [
            'name' => 'Still Same',
            'email' => 'moved@example.com',
        ])->assertOk();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verification_code_is_stubbed_until_a_channel_is_live(): void
    {
        $user = User::factory()->student()->unverified()->create();

        $this->actingAs($user)->postJson('/api/profile/verify-email')
            ->assertStatus(503)
            ->assertJsonPath('ok', false);
    }

    public function test_verification_code_can_be_sent_and_confirmed(): void
    {
        config(['services.email_verification.enabled' => true]);

        $user = User::factory()->student()->unverified()->create();

        $this->actingAs($user)->postJson('/api/profile/verify-email')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNotNull($user->fresh()->email_verification_code);

        // A wrong code is rejected.
        $this->actingAs($user)->postJson('/api/profile/verify-email/confirm', ['code' => '000000'])
            ->assertStatus(422);

        // The real code (stored hashed) is accepted.
        $code = '123456';
        $user->forceFill([
            'email_verification_code' => Hash::make($code),
            'email_verification_expires_at' => now()->addMinutes(15),
        ])->save();

        $this->actingAs($user)->postJson('/api/profile/verify-email/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_already_verified_email_needs_no_code(): void
    {
        $user = User::factory()->student()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->postJson('/api/profile/verify-email')
            ->assertOk()
            ->assertJsonPath('already_verified', true);
    }

    public function test_admin_can_read_and_update_any_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create(['name' => 'Student One']);
        $profile = Student::factory()->create(['user_id' => $student->id]);

        $this->actingAs($admin)->getJson("/api/admin/users/{$student->id}/profile")
            ->assertOk()
            ->assertJsonPath('user.name', 'Student One')
            ->assertJsonPath('user.role', 'student');

        $this->actingAs($admin)->putJson("/api/admin/users/{$student->id}/profile", [
            'name' => 'Renamed By Admin',
            'email' => $student->email,
            'phone' => '09181234567',
        ])->assertOk()->assertJsonPath('user.name', 'Renamed By Admin');

        $this->assertDatabaseHas('users', ['id' => $student->id, 'name' => 'Renamed By Admin']);
        $this->assertDatabaseHas('students', ['id' => $profile->id, 'phone' => '09181234567']);
    }

    public function test_admin_can_reset_another_users_password(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create(['password' => Hash::make('oldpassword')]);

        $this->actingAs($admin)->putJson("/api/admin/users/{$student->id}/profile", [
            'name' => $student->name,
            'email' => $student->email,
            'password' => 'resetpassword123',
        ])->assertOk();

        $this->assertTrue(Hash::check('resetpassword123', $student->fresh()->password));
    }

    public function test_non_admin_cannot_edit_another_users_profile(): void
    {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();

        $this->actingAs($student)->getJson("/api/admin/users/{$other->id}/profile")
            ->assertStatus(403);
    }
}
