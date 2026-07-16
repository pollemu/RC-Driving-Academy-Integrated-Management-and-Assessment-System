<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'format', 'fee', 'downpayment', 'is_active'])]
class Course extends Model
{
    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'downpayment' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(Certification::class);
    }
}
