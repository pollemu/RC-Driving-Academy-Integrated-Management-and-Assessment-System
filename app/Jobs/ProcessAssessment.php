<?php

namespace App\Jobs;

use App\Models\Assessment;
use App\Models\Feedback;
use App\Services\NlpClassifier;
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

        if ($this->feedback?->id && (new NlpClassifier())->hasSafetyKeyword($this->feedback->raw_text)) {
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
            'engine'  => $this->feedback->star_engine,
            'brake'   => $this->feedback->star_brake,
            'turning' => $this->feedback->star_turning,
            'parking' => $this->feedback->star_parking,
            'traffic' => $this->feedback->star_traffic,
        ];

        $notes = [
            'engine'  => $this->feedback->note_engine,
            'brake'   => $this->feedback->note_brake,
            'turning' => $this->feedback->note_turning,
            'parking' => $this->feedback->note_parking,
            'traffic' => $this->feedback->note_traffic,
        ];

        $nlp = new NlpService();
        $nlp->train([
            'text' => $this->feedback->raw_text,
            'label' => (new NlpClassifier())->hasSafetyKeyword($this->feedback->raw_text)
                ? 'needs'
                : $assessment->classification,
            'stars' => $stars,
            'notes' => $notes,
            'schedule_id' => $this->feedback->schedule_id,
            'student_id' => $this->feedback->student_id,
        ]);
    }
}
