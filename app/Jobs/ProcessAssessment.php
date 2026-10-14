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
use Illuminate\Support\Facades\Log;

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
        // Thestructor's API already created a rule-based Assessment synchronously
        // (so the student sees a result instantly, even if the NLP service is
        // down). This job ENRICHES that same assessment with the NLP model and
        // feeds the feedback back into the model for continual training.
        $assessment = $this->feedback->assessment;

        if (! $assessment) {
            // Shouldn't happen (the controller pre-creates it), but stay safe.
            return;
        }

        $this->trainModel($assessment);

        $nlp = new NlpService();
        $result = $nlp->classify($this->feedback->raw_text);

        if ($result === null) {
            // Flask is down — release so the queue retries later (training
            // was best-effort and the rule-based result already stands).
            $this->release($this->backoff);

            return;
        }

        $classification = $result['classification'] ?? $assessment->classification;
        $confidence = $result['confidence'] ?? $assessment->confidence_score;
        $recommendation = $result['recommendation'] ?? $assessment->recommendation;
        $override = ! empty($result['override_applied']);

        if ($this->hasSafetyKeyword($this->feedback->raw_text)) {
            $classification = 'needs';
            $recommendation = 'Mandatory intervention. Extended hours required before reassessment.';
            $override = true;
        }

        $assessment->update([
            'classification' => $classification,
            'confidence_score' => $confidence,
            'recommendation' => $recommendation,
            'override_applied' => $override,
            'nlp_enriched' => true,
        ]);
    }

/* Sends this assessment's feedback to the NLP service so its classifier
       is retrained/improved on every real instructor submission. The label
       taught is the (pre-NLP) rule-based classification — our answer key. */
    protected function trainModel(Assessment $assessment): void
    {
        $stars = [
            'steering' => $this->feedback->star_steering,
            'speed'    => $this->feedback->star_speed,
            'lane'     => $this->feedback->star_lane,
            'parking'  => $this->feedback->star_parking,
            'safety'   => $this->feedback->star_safety,
            'mirror'   => $this->feedback->star_mirror,
        ];

        $notes = [
            'steering' => $this->feedback->note_steering,
            'speed'    => $this->feedback->note_speed,
            'lane'     => $this->feedback->note_lane,
            'parking'  => $this->feedback->note_parking,
            'safety'   => $this->feedback->note_safety,
            'mirror'   => $this->feedback->note_mirror,
        ];

        $nlp = new NlpService();
        $nlp->train([
            'text' => $this->feedback->raw_text,
            'label' => $this->hasSafetyKeyword($this->feedback->raw_text) ? 'needs' : $assessment->classification,
            'stars' => $stars,
            'notes' => $notes,
            'schedule_id' => $this->feedback->schedule_id,
            'student_id' => $this->feedback->student_id,
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
