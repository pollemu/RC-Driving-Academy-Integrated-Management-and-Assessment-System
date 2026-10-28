<?php

use App\Services\AssessmentSuggestion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Brings historical PDC assessments in line with the new three-skill model.
 * Suggestions and scores were calculated from the old six ratings; refresh
 * them so a student never sees "Parking" advice next to a steering/braking/
 * side-mirror scorecard. NLP-enriched verdicts are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('feedback')
            ->whereNotNull('star_steering')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $f) {
                    $assessment = DB::table('assessments')
                        ->where('feedback_id', $f->id)
                        ->first();

                    if (! $assessment) {
                        continue;
                    }

                    $stars = [
                        'steering' => $f->star_steering,
                        'brake'    => $f->star_brake,
                        'mirror'   => $f->star_mirror,
                    ];
                    $notes = [
                        'steering' => $f->note_steering,
                        'brake'    => $f->note_brake,
                        'mirror'   => $f->note_mirror,
                    ];
                    $built = AssessmentSuggestion::build($stars, $notes);

                    $values = array_filter(array_map('intval', $stars), fn ($v) => $v > 0);
                    $score = $values
                        ? round(array_sum($values) / count($values) / 5 * 100, 2)
                        : null;

                    if ($score !== null && $assessment->exam_score !== null) {
                        $score = round(($score + (float) $assessment->exam_score) / 2, 2);
                    }

                    $update = ['suggestions' => json_encode($built['suggestions'])];

                    if ($score !== null) {
                        $update['score'] = $score;
                        $update['passed'] = $score >= 75;
                    }

                    // Keep any verdict the NLP model already produced.
                    if (! $assessment->nlp_enriched) {
                        $update['classification'] = AssessmentSuggestion::classify($stars);
                    }

                    DB::table('assessments')->where('id', $assessment->id)->update($update);
                }
            });
    }

    public function down(): void
    {
        // Data-only migration — nothing to reverse.
    }
};
