<?php

namespace App\Jobs;

use App\Models\Assessment;
use App\Models\Feedback;
use App\Services\NlpService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAssessment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $backoff = 30;

    public function __construct(public Feedback $feedback)
    {
    }

    public function handle(): void
    {
        if ($this->feedback->assessment) {
            return;
        }

        $nlp = new NlpService();
        $result = $nlp->classify($this->feedback->raw_text);

        if ($result === null) {
            // Flask is down — throw to trigger a retry via the queue.
            $this->release($this->backoff);
            return;
        }

        $classification = $result['classification'] ?? 'average';
        $confidence = $result['confidence'] ?? null;
        $recommendation = $result['recommendation'] ?? '';
        $override = ! empty($result['override_applied']);

        if ($this->hasSafetyKeyword($this->feedback->raw_text)) {
            $classification = 'needs';
            $recommendation = 'Mandatory intervention. Extended hours required before reassessment.';
            $override = true;
        }

        Assessment::create([
            'feedback_id' => $this->feedback->id,
            'classification' => $classification,
            'confidence_score' => $confidence,
            'recommendation' => $recommendation,
            'override_applied' => $override,
        ]);
    }

    protected function hasSafetyKeyword(string $text): bool
    {
        $keywords = [
            'bumangga', 'collision', 'near-collision', 'dangerous', 'hazard', 'crash',
            'accident', 'took control', 'had to take control', 'emergency',
            'unsafe speed', 'erratic', 'delikado', 'mapanganib', 'muntik na kaming bumangga',
        ];

        $lower = mb_strtolower($text);

        foreach ($keywords as $keyword) {
            if (str_contains($lower, mb_strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }
}
