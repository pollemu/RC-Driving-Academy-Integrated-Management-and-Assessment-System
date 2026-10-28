<?php

namespace App\Services;

/**
 * Builds a per-category training suggestion for a PDC assessment.
 *
 * The instructor rates each driving skill (1-5 stars) and may attach a
 * preset sentence ("Perfect / Average / Low driving skills"). For any category
 * rated below the PASS_THRESHOLD we suggest a concrete training module.
 *
 * This is intentionally rule-based (no ML model required) so it works in
 * the live app immediately; the NLP service can be layered on top later.
 */
class AssessmentSuggestion
{
    public const PASS_THRESHOLD = 3;

    /** category key => [label, weak training module]. PDC rates three skills. */
    public const CATEGORIES = [
        'steering' => ['Steering Control', 'Steering precision drills — cone weave & low-speed control'],
        'brake'    => ['Braking', 'Braking control — smooth stops, pedal feel & safe following distance'],
        'mirror'   => ['Side Mirror & Observation', 'Side-mirror and blind-spot observation exercises'],
    ];

    /**
     * @param  array<int|null>  $stars  category => star rating (1-5)
     * @param  array<string>   $notes  category => preset sentence (optional)
     * @return array{failed:array, suggestions:array}  list of weak categories with their tip
     */
    public static function build(array $stars, array $notes = []): array
    {
        $failed = [];
        $suggestions = [];

        foreach (self::CATEGORIES as $key => [$label, $module]) {
            $score = (int) ($stars[$key] ?? 0);
            if ($score === 0 || $score < self::PASS_THRESHOLD) {
                $failed[] = $key;
                $note = $notes[$key] ?? null;
                $suggestions[] = [
                    'category'      => $key,
                    'label'         => $label,
                    'score'         => $score,
                    'instructor_note' => $note,
                    'module'        => $module,
                ];
            }
        }

        return ['failed' => $failed, 'suggestions' => $suggestions];
    }

    /** Overall classification from the weakest average (fast / average / needs). */
    public static function classify(array $stars): string
    {
        $vals = array_filter(array_map('intval', $stars), fn ($v) => $v > 0);
        if (empty($vals)) {
            return 'average';
        }
        $avg = array_sum($vals) / count($vals);

        if ($avg >= 4.5) {
            return 'fast';
        }
        if ($avg < self::PASS_THRESHOLD) {
            return 'needs';
        }

        return 'average';
    }
}
