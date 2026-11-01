<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\NlpEvaluator;
use App\Services\NlpService;
use Illuminate\Http\Request;

class NlpController extends Controller
{
    /* Classifies free-text assessment feedback with the live learner. */
    public function classify(Request $request)
    {
        $request->validate([
            'text' => 'required|string|max:2000',
        ]);

        $result = (new NlpService())->classify($request->input('text'));

        if ($result === null) {
            return response()->json([
                'classification' => null,
                'confidence' => 0,
                'recommendation' => 'The model has no training data yet. Teach it by grading assessments.',
                'model' => 'bayes-php',
                'training_size' => (new NlpService())->trainingSize(),
                'tokens' => [],
            ]);
        }

        return response()->json($result);
    }

    /* Live evaluation for the admin "Grading AI" model card: the same
       stratified 70/30 methodology as `php artisan nlp:evaluate`, plus
       corpus composition stats (class balance and Filipino/English mix). */
    public function evaluate(Request $request)
    {
        $evaluator = new NlpEvaluator();
        $result = $evaluator->evaluate(0.7, 42);

        $labels = [
            'fast' => 'Fast Learner',
            'average' => 'Average Learner',
            'needs' => 'Needs Improvement',
        ];

        // Classes that have zero labeled examples yet (cold start notice).
        $empty = array_keys(array_filter($result['per_class'] ?? [], fn ($n) => $n === 0));

        return response()->json([
            'model' => 'multinomial-naive-bayes',
            'features' => 'unigrams + bigrams',
            'languages' => ['Filipino', 'English', 'Taglish'],
            'labels' => $labels,
            'total' => $result['total'],
            'train_size' => $result['train_size'],
            'test_size' => $result['test_size'],
            'correct' => $result['correct'],
            'accuracy' => $result['accuracy'],
            'confusion' => $result['confusion'],
            'recall' => $result['recall'],
            'per_class' => $result['per_class'],
            'per_language' => $result['per_language'],
            'vocab_size' => $result['vocab_size'],
            'empty_classes' => $empty,
        ]);
    }
}
