<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['raw_text', 'label', 'stars', 'notes', 'student_id', 'schedule_id'])]

class AiTrainingExample extends Model
{
    protected function casts(): array
    {
        return [
            'stars' => 'array',
            'notes' => 'array',
        ];
    }
}