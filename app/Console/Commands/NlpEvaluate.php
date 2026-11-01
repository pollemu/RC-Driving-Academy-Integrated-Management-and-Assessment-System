<?php

namespace App\Console\Commands;

use App\Services\NlpClassifier;
use App\Services\NlpEvaluator;
use Illuminate\Console\Command;

/**
 * Offline evaluation of the Multinomial Naive Bayes classifier (with bigram
 * features) over the labeled corpus in `ai_training_examples` (run
 * `php artisan db:seed --class=NlpTrainingSeeder` for the 500+ corpus).
 *
 * Methodology lives in App\Services\NlpEvaluator so the Chapter 4 tables and
 * the live admin "Grading AI" model card always report identical numbers:
 *   1. Stratified 70/30 split (deterministic seeded shuffle).
 *   2. The training half builds the multinomial Naive Bayes model with
 *      Laplace smoothing over unigram + bigram features — the exact math of
 *      NlpClassifier::classify().
 *   3. The held-out half is classified one sentence at a time and compared
 *      against its true label.
 *
 * Outputs:
 *   Table 4-24 — Training/Testing size, correct predictions, accuracy
 *   Table 4-25 — 3x3 confusion matrix (Fast / Average / Needs Improvement)
 */
class NlpEvaluate extends Command
{
    protected $signature = 'nlp:evaluate {--seed=42 : RNG seed for the split} {--split=0.7 : training proportion} {--errors : list misclassified examples}';

    protected $description = 'Evaluate the performance classifier on a stratified 70/30 split (Chapter 4 tables)';

    public function handle(): int
    {
        $result = (new NlpEvaluator())->evaluate((float) $this->option('split'), (int) $this->option('seed'));

        if ($result['total'] === 0) {
            $this->error('No labeled examples found. Run: php artisan db:seed --class=NlpTrainingSeeder');

            return 1;
        }

        // ---------- Output: Chapter 4 tables ----------
        $this->line('');
        $this->line('Table 4-24. Performance evaluation of the Naive Bayes classifier');
        $this->table(
            ['Training Size', 'Testing Size', 'Correct Predictions', 'Predictive Accuracy'],
            [[
                (string) $result['train_size'],
                (string) $result['test_size'],
                (string) $result['correct'],
                sprintf('%.2f %%', $result['accuracy'] * 100),
            ]]
        );

        $this->line('');
        $this->line('Table 4-25. Confusion matrix (rows = actual, columns = predicted)');
        $display = ['fast' => 'Fast Learner', 'average' => 'Average Learner', 'needs' => 'Needs Improvement'];
        $rows = [];
        foreach (NlpClassifier::LABELS as $actual) {
            $rows[] = [
                $display[$actual],
                $result['confusion'][$actual]['fast'],
                $result['confusion'][$actual]['average'],
                $result['confusion'][$actual]['needs'],
                sprintf('%.2f %%', $result['recall'][$actual] * 100),
            ];
        }
        $this->table(
            ['Actual \\ Predicted', 'Fast Learner', 'Average Learner', 'Needs Improvement', 'Recall'],
            $rows
        );

        if ($this->option('errors') && $result['errors']) {
            $this->line('');
            $this->line('Misclassified examples:');
            $this->table(
                ['Actual', 'Predicted', 'Text'],
                array_map(
                    fn (array $e) => [$display[$e['actual']], $display[$e['predicted']], $e['text']],
                    array_slice($result['errors'], 0, 25)
                )
            );
        }

        return 0;
    }
}
