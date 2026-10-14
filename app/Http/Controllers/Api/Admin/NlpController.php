<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
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
}