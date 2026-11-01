<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'email', 'password', 'role', 'is_active', 'avatar_path',
    'kyc_status', 'id_type', 'id_document_path', 'selfie_path',
    'liveness_passed', 'auto_verified', 'kyc_submitted_at',
    'kyc_verified_at', 'kyc_verified_by', 'kyc_rejection_reason',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
/** @use HasFactory<UserFactory> */
use HasFactory, Notifiable, HasApiTokens;

    /* KYC (Know-Your-Customer) identity verification states. */
    public const KYC_UNVERIFIED = 'unverified'; // signup done, nothing submitted
    public const KYC_PENDING = 'pending';      // submitted, awaiting admin review
    public const KYC_VERIFIED = 'verified';    // admin approved
    public const KYC_REJECTED = 'rejected';    // admin rejected; student can resubmit

    public function hasVerifiedKyc(): bool
    {
        return $this->kyc_status === self::KYC_VERIFIED;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_expires_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'face_match_score' => 'decimal:4',
            'liveness_passed' => 'boolean',
            'auto_verified' => 'boolean',
            'kyc_submitted_at' => 'datetime',
            'kyc_verified_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isInstructor(): bool
    {
        return $this->role === 'instructor';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function student()
    {
        return $this->hasOne(Student::class);
    }

    public function instructor()
    {
        return $this->hasOne(Instructor::class);
    }

    /* Admin who reviewed the KYC submission (users.kyc_verified_by). */
    public function kycVerifiedBy()
    {
        return $this->belongsTo(User::class, 'kyc_verified_by');
    }
}
