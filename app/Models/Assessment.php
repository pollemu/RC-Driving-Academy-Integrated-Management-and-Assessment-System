<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['feedback_id', 'classification', 'confidence_score', 'recommendation', 'override_applied', 'suggestions'])]

class Assessment extends Model
{
    protected function casts(): array
    {
        return [
            'confidence_score' => 'decimal:2',
            'override_applied' => 'boolean',
            'suggestions' => 'array',
        ];
    }

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(Feedback::class);
    }
}
