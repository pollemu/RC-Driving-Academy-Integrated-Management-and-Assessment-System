<?php

namespace App\Services;

use App\Models\AiTrainingExample;

/**
 * Supervised-learning grading assistant built into the app (no external AI
 * server required).
 *
 * The instructor's written assessment feedback IS the training data: every
 * verified submission is stored as a labeled example (the rule-based
 * classification acts as the answer key) and a multinomial Naive Bayes model
 * is re-derived from those examples on every call. The more instructors grade,
 * the better it classifies — "teach the AI by grading, keep grading as the AI
 * learns."
 *
 * Features: filtered unigram word counts PLUS bigram pairs ("smooth_braking",
 * "napakakinis_ng") built from consecutive retained words — word pairs carry
 * the sentiment wording ("paulit-ulit_biglaan", "first_attempt_parallel")
 * that single words miss. This is how the Multinomial Naive Bayes model and
 * the bigram features work together as one classifier.
 *
 * Bilingual by design: the corpus mixes English, Filipino (Tagalog) and
 * code-switched Taglish, so the model reads Filipino/English assessments
 * with one shared vocabulary. The Filipino/English sentiment wording —
 * "magaling/napakahusay" (positive) vs. "delikado/paulit-ulit na mali"
 * (negative) — is what fast/average/needs sentiment classification feeds
 * on, and the tokenSignals() chips make it visible in the UI.
 *
 * The model degrades gracefully: with no training data yet it returns null so
 * callers keep their rule-based result untouched.
 */
class NlpClassifier
{
    public const LABELS = ['fast', 'average', 'needs'];

    /** Generic filler that adds no signal (English + Tagalog lexicon). */
    private const STOPWORDS = [
        'the', 'a', 'an', 'and', 'or', 'but', 'with', 'for', 'on', 'of', 'to',
        'in', 'is', 'was', 'were', 'are', 'be', 'been', 'it', 'its', 'this',
        'that', 'he', 'she', 'his', 'her', 'they', 'their', 'them', 'i', 'you',
        'we', 'as', 'at', 'by', 'from', 'about', 'more', 'very', 'through',
        'throughout', 'can', 'has', 'had', 'have', 'not', 'no', 'but', 'so',
        'student', 'shows', 'show', 'session', 'assessment', 'feedback',
        // Tagalog fillers — pure grammar words that carry no rating signal.
        'ang', 'mga', 'yung', 'ung', 'naman', 'kasi', 'habang', 'lang',
        'din', 'rin', 'pala', 'ako', 'ikaw', 'siya', 'nila', 'sila', 'namin',
        'amin', 'kanya', 'kanila', 'sa', 'na', 'ng', 'nang', 'si', 'ni',
        'ka', 'mo', 'ko', 'tayo', 'kayo', 'daw', 'raw', 'upang', 'kung',
        'kapag', 'pag', 'dahil', 'ganun', 'ganito', 'ito', 'iyan',
        'iyun', 'ano', 'talaga',
        'may', 'meron', 'noon', 'ngayon', 'kaya', 'sana', 'parang', 'siguro',
        // 'hindi'/'wala' (not/none) are intentionally KEPT as tokens — negation
        // is strong evidence ("hindi sumusunod sa stop sign" => needs training).
        // 'pero' is also KEPT — the "strength PERO weakness" pivot is the
        // signature of the Average Learner wording. 'lagi'/'madalas'/'halos'
        // stay tokens too — they typically describe recurring problems.
    ];

    /**
     * Learns "on the job": classifies with the whole current training set.
     *
     * @return array{classification:string,confidence:float,recommendation:string,override_applied:bool,model:string,training_size:int,tokens:array<int,array{t:string,c:string}>}|null
     */
    public function classify(string $text): ?array
    {
        $examples = AiTrainingExample::query()
            ->whereNotNull('label')
            ->where('label', '!=', '')
            ->get(['raw_text', 'label']);

        $totalDocs = $examples->count();
        if ($totalDocs === 0) {
            // Cold start — nothing has been taught yet.
            return null;
        }

        $docCount = [];
        $tokenCounts = [];
        $vocab = [];

        foreach ($examples as $example) {
            $label = $example->label;
            $docCount[$label] = ($docCount[$label] ?? 0) + 1;

            foreach (array_count_values($this->features($example->raw_text)) as $token => $count) {
                $vocab[$token] = true;
                $tokenCounts[$label][$token] = ($tokenCounts[$label][$token] ?? 0) + $count;
            }
        }

        $vocabSize = count($vocab);
        $queryTokens = $this->features($text);

        $scores = [];
        foreach (self::LABELS as $label) {
            $prior = ($docCount[$label] ?? 0) / $totalDocs;
            if ($prior <= 0) {
                // Class never seen yet — a tiny prior beats -INF.
                $prior = 0.01;
            }
            $score = log($prior);

            $labelTokens = $tokenCounts[$label] ?? [];
            $sumTokens = array_sum($labelTokens);

            foreach ($queryTokens as $token) {
                $seen = $labelTokens[$token] ?? 0;
                // Laplace smoothing so unseen words never zero out a class.
                $score += log(($seen + 1) / ($sumTokens + $vocabSize));
            }

            $scores[$label] = $score;
        }

        arsort($scores);
        $best = array_key_first($scores);
        $second = array_values($scores)[1];

        // Softmax over the three log-scores -> honest posterior confidence.
        $max = max($scores);
        $exp = [];
        foreach ($scores as $label => $score) {
            $exp[$label] = exp($score - $max);
        }
        $sum = array_sum($exp);
        $confidence = $sum > 0 ? $exp[$best] / $sum : 0.0;

        // Safety override: a near-miss narrated in the feedback must NEVER be
        // graded positively, whatever the word statistics say.
        $override = false;
        if ($this->hasSafetyKeyword($text)) {
            $best = 'needs';
            $override = true;
            $confidence = max($confidence, 0.9);
        }

        return [
            'classification' => $best,
            'confidence' => round($confidence, 4),
            'recommendation' => $this->recommendation($best, $override),
            'override_applied' => $override,
            'model' => 'bayes-php',
            'training_size' => $totalDocs,
            'tokens' => $this->tokenSignals($queryTokens, $scores, $tokenCounts, $vocabSize),
        ];
    }

    /** Stores one labeled training example. */
    public function train(array $payload): bool
    {
        $text = trim((string) ($payload['text'] ?? ''));
        if ($text === '') {
            return false;
        }

        $label = $payload['label'] ?? $this->labelFromStars($payload['stars'] ?? []);
        if (! in_array($label, self::LABELS, true)) {
            return false;
        }

        AiTrainingExample::create([
            'raw_text' => $text,
            'label' => $label,
            'stars' => $payload['stars'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'student_id' => $payload['student_id'] ?? null,
            'schedule_id' => $payload['schedule_id'] ?? null,
        ]);

        return true;
    }

    /** Total labeled examples currently in the learned model. */
    public function trainingSize(): int
    {
        return AiTrainingExample::whereNotNull('label')->where('label', '!=', '')->count();
    }

    protected function recommendation(string $label, bool $override = false): string
    {
        if ($override) {
            return 'Mandatory intervention. Extended hours required before reassessment.';
        }

        return match ($label) {
            'fast' => 'Excellent performance. Continue at current pace.',
            'average' => 'Steady progress. Focus on the weaker areas listed.',
            default => 'Instructor intervention recommended. Additional sessions advised.',
        };
    }

    /** Near-miss vocabulary in either language — narrating a close call
     * outweighs any positive wording in the same sentence. */
    public function hasSafetyKeyword(string $text): bool
    {
        $keywords = [
            'bumangga', 'collision', 'near-collision', 'dangerous', 'hazard', 'crash',
            'accident', 'took control', 'had to take control', 'emergency',
            'unsafe speed', 'erratic', 'delikado', 'mapanganib', 'muntik na kaming bumangga',
            'almost hit', 'nearly hit', 'almost collided', 'nearly collided',
            'hit the curb', 'hit the sidewalk', 'bumped into', 'sideswipe',
            'halos mabangga', 'muntik', 'sumalpok', 'sumabit sa', 'nabundol',
            'salpukan', 'banggaan', 'untog', 'naanod sa kanal', 'naipit sa',
        ];

        $lower = mb_strtolower($text);

        foreach ($keywords as $keyword) {
            if (str_contains($lower, mb_strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    /** Derives the answer key from star ratings using the same rule as grading. */
    protected function labelFromStars(array $stars): string
    {
        return AssessmentSuggestion::classify($stars);
    }

    /** Lowercase, strip punctuation, drop filler words. Made public so the
     * evaluation command tokenizes with the exact same rules as this model. */
    public function tokenize(string $text): array
    {
        $lower = mb_strtolower($text);
        // Split on anything that is not a letter or hyphen (keeps "near-miss").
        $words = preg_split('/[^a-z\x{00C0}-\x{00FF}-]+/u', $lower) ?: [];

        $tokens = [];
        foreach ($words as $word) {
            if ($word === '' || mb_strlen($word) < 3) {
                continue;
            }
            if (in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            $tokens[] = $word;
        }

        return $tokens;
    }

    /**
     * Model features: filtered unigrams PLUS bigrams of consecutive retained
     * words ("pair_two_words" style underscore tokens). They live in one
     * vocabulary so the MNB math is unchanged — a bigram is just a feature
     * that cannot collide with a single word.
     */
    public function features(string $text): array
    {
        $unigrams = $this->tokenize($text);
        $features = $unigrams;

        for ($i = 1; $i < count($unigrams); $i++) {
            $features[] = $unigrams[$i - 1] . '_' . $unigrams[$i];
        }

        return $features;
    }

    /** True when a feature token is a bigram (used by the UI chips). */
    public static function isBigram(string $token): bool
    {
        return str_contains($token, '_');
    }

    /**
     * For each word in the classified text, which class does it pull toward?
     * Lets the UI show the reasoning behind the verdict.
     *
     * @return array<int, array{t:string, c:string}>  c: pos|neg|'' (CSS chip class)
     */
    protected function tokenSignals(array $queryTokens, array $scores, array $tokenCounts, int $vocabSize): array
    {
        $signals = [];
        $seen = [];

        foreach ($queryTokens as $token) {
            if (isset($seen[$token])) {
                continue;
            }
            $seen[$token] = true;

            $bestLabel = null;
            $bestScore = -INF;
            foreach (self::LABELS as $label) {
                $priorScore = $scores[$label];
                $seenCount = $tokenCounts[$label][$token] ?? 0;
                $sumTokens = array_sum($tokenCounts[$label] ?? []);
                $tokenScore = $priorScore + log(($seenCount + 1) / ($sumTokens + $vocabSize));
                if ($bestLabel === null || $tokenScore > $bestScore) {
                    $bestLabel = $label;
                    $bestScore = $tokenScore;
                }
            }

            $signals[] = [
                't' => $token,
                'c' => $bestLabel === 'fast' ? 'pos' : ($bestLabel === 'needs' ? 'neg' : ''),
            ];
        }

        return $signals;
    }
}