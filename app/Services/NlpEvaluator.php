<?php

namespace App\Services;

use App\Models\AiTrainingExample;

/**
 * Stratified train/test evaluation of the Multinomial Naive Bayes +
 * bigram-features performance classifier, shared by the artisan command
 * (Chapter 4 tables) and the live admin "Grading AI" model card.
 *
 * Methodology:
 *   1. Labeled examples in `ai_training_examples` are split per class,
 *      `ratio` into training and the rest held out for testing
 *      (deterministic seeded shuffle so the manuscript numbers reproduce).
 *   2. The training half builds the multinomial Naive Bayes model with
 *      Laplace smoothing over the SAME feature space as
 *      NlpClassifier::classify() (unigrams + bigrams).
 *   3. The held-out half is classified one sentence at a time and compared
 *      against its true label.
 */
class NlpEvaluator
{
    /** Filipino function/valence words used only for the language mix stat. */
    private const TL_MARKERS = [
        'siya', 'niya', 'nila', 'naman', 'kasi', 'pero', 'kung', 'ang', 'ng', 'mga', 'yung', 'sa', 'na',
        'tama', 'mali', 'lagi', 'madalas', 'minsan', 'kadalasan', 'hindi', 'wala', 'meron',
        'magaling', 'galing', 'hina', 'kulang', 'sapat', 'ayos', 'bilis', 'gulong', 'manibela',
        'preno', 'pumreno', 'makina', 'sasakyan', 'daan', 'akselerasyon', 'pagpreno', 'pagliko',
        'lipat', 'tumpak', 'malumanay', 'biglaan', 'grabeng', 'napakahusay', 'disiplina', 'pa',
    ];

    /* English content words almost exclusive to the EN-only wording. */
    private const EN_MARKERS = [
        'the', 'was', 'were', 'always', 'every', 'which', 'where', 'with', 'without',
        'displayed', 'maintained', 'required', 'needed', 'struggles', 'nearly', 'during', 'throughout',
    ];

    protected NlpClassifier $classifier;

    public function __construct()
    {
        $this->classifier = new NlpClassifier();
    }

    /**
     * @param  float  $ratio  training proportion (0..1)
     * @param  int    $seed   RNG seed for the deterministic split
     * @return array{
     *   total:int, train_size:int, test_size:int, correct:int, accuracy:float,
     *   confusion:array<string,array<string,int>>, recall:array<string,float>, precision:array<string,float>,
     *   per_class:array<string,int>, per_language:array<string,int>, vocab_size:int, errors:array<int,array{actual:string,predicted:string,text:string}>
     * }
     */
    public function evaluate(float $ratio = 0.7, int $seed = 42): array
    {
        $examples = AiTrainingExample::query()
            ->whereNotNull('label')
            ->where('label', '!=', '')
            ->orderBy('id')
            ->get(['raw_text', 'label']);

        $perClass = [];
        $perLanguage = ['english' => 0, 'filipino' => 0, 'taglish' => 0];
        foreach ($examples as $example) {
            $perClass[$example->label] = ($perClass[$example->label] ?? 0) + 1;
            $lang = $this->guessLanguage($example->raw_text);
            $perLanguage[$lang] = ($perLanguage[$lang] ?? 0) + 1;
        }

        $display = NlpClassifier::LABELS;
        if ($examples->isEmpty()) {
            return [
                'total' => 0, 'train_size' => 0, 'test_size' => 0, 'correct' => 0, 'accuracy' => 0.0,
                'confusion' => [], 'recall' => [], 'precision' => [],
                'per_class' => $perClass, 'per_language' => $perLanguage, 'vocab_size' => 0, 'errors' => [],
            ];
        }

        // ---------- 1) Stratified split ----------
        $byLabel = [];
        foreach (NlpClassifier::LABELS as $label) {
            $byLabel[$label] = $examples->where('label', $label)->shuffle($seed)->values();
        }

        $train = [];
        $test = [];
        foreach ($byLabel as $label => $rows) {
            $trainCount = (int) floor($rows->count() * $ratio);
            foreach ($rows as $i => $row) {
                if ($i < $trainCount) {
                    $train[] = $row;
                } else {
                    $test[] = $row;
                }
            }
        }

        // ---------- 2) Build the MNB model from the training half ----------
        // Multinomial term counts over the unigram+bigram feature space,
        // identical to NlpClassifier::classify() (Laplace smoothing).
        $tokenCounts = [];
        $vocab = [];
        $docCount = [];
        foreach ($train as $row) {
            $docCount[$row->label] = ($docCount[$row->label] ?? 0) + 1;
            foreach (array_count_values($this->classifier->features($row->raw_text)) as $feature => $count) {
                $vocab[$feature] = true;
                $tokenCounts[$row->label][$feature] = ($tokenCounts[$row->label][$feature] ?? 0) + $count;
            }
        }
        $vocabSize = count($vocab);
        $totalDocs = count($train);

        $predict = function (string $text) use ($tokenCounts, $vocabSize, $docCount, $totalDocs): string {
            $scores = [];
            foreach (NlpClassifier::LABELS as $label) {
                $prior = ($docCount[$label] ?? 0) / $totalDocs;
                if ($prior <= 0) {
                    $prior = 0.01;
                }
                $score = log($prior);
                $labelTokens = $tokenCounts[$label] ?? [];
                $sumTokens = array_sum($labelTokens);
                foreach ($this->classifier->features($text) as $feature) {
                    $seen = $labelTokens[$feature] ?? 0;
                    $score += log(($seen + 1) / ($sumTokens + $vocabSize));
                }
                $scores[$label] = $score;
            }
            arsort($scores);

            return array_key_first($scores);
        };

        // ---------- 3) Test on the held-out half ----------
        $confusion = [];
        foreach (NlpClassifier::LABELS as $actual) {
            foreach (NlpClassifier::LABELS as $predicted) {
                $confusion[$actual][$predicted] = 0;
            }
        }
        $correct = 0;
        $errors = [];
        foreach ($test as $row) {
            $predicted = $predict($row->raw_text);
            $confusion[$row->label][$predicted]++;
            if ($predicted === $row->label) {
                $correct++;
            } else {
                $errors[] = ['actual' => $row->label, 'predicted' => $predicted, 'text' => $row->raw_text];
            }
        }

        $recall = [];
        $precision = [];
        foreach (NlpClassifier::LABELS as $label) {
            $actualTotal = array_sum($confusion[$label]);
            $colSum = 0;
            foreach (NlpClassifier::LABELS as $a) {
                $colSum += $confusion[$a][$label];
            }
            $recall[$label] = $actualTotal > 0 ? $confusion[$label][$label] / $actualTotal : 0.0;
            $precision[$label] = $colSum > 0 ? $confusion[$label][$label] / $colSum : 0.0;
        }

        return [
            'total' => $examples->count(),
            'train_size' => $totalDocs,
            'test_size' => count($test),
            'correct' => $correct,
            'accuracy' => $test !== [] ? $correct / count($test) : 0.0,
            'confusion' => $confusion,
            'recall' => $recall,
            'precision' => $precision,
            'per_class' => $perClass,
            'per_language' => $perLanguage,
            'vocab_size' => $vocabSize,
            'errors' => $errors,
        ];
    }

    /**
     * Coarse language tag for the corpus-mix display: word-level Taglish is
     * normal in real instructor feedback, so a sentence with both Filipino
     * markers and English connectors is classified as Taglish.
     */
    public function guessLanguage(string $text): string
    {
        $words = preg_split('/[^a-z]+/i', mb_strtolower($text)) ?: [];
        $tl = 0;
        $en = 0;
        foreach ($words as $w) {
            if ($w === '') {
                continue;
            }
            if (in_array($w, self::TL_MARKERS, true)) {
                $tl++;
            } elseif (in_array($w, self::EN_MARKERS, true)) {
                $en++;
            }
        }

        if ($tl > 0 && $en > 0) {
            return 'taglish';
        }
        if ($tl > 0) {
            return 'filipino';
        }

        return 'english';
    }
}
