<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Assessment NLP gateway.
 *
 * NLP_DRIVER=local    (default) the bundled PHP Naive Bayes learner runs
 *                     entirely inside the app — it stores every verified
 *                     assessment as a labeled example and re-learns on the
 *                     fly, so no separate AI server is needed.
 * NLP_DRIVER=external any ML_ENABLED endpoint (e.g. a Flask/sklearn server)
 *                     that speaks /classify + /train. Set as soon as a real
 *                     model exists to plug it in without touching callers.
 */
class NlpService
{
    protected NlpClassifier $classifier;

    protected string $driver;

    protected string $url;

    public function __construct()
    {
        $this->classifier = new NlpClassifier();
        $this->driver = (string) config('services.nlp.driver', 'local');
        $this->url = rtrim((string) config('services.nlp.url', ''), '/');
    }

    /**
     * @return array{classification:string,confidence:float,recommendation:string,override_applied:bool,model:string,training_size:int,tokens:array}|null
     */
    public function classify(string $text): ?array
    {
        if ($this->driver === 'external' && $this->url !== '') {
            try {
                $response = Http::timeout(5)->post($this->url . '/classify', [
                    'text' => $text,
                ]);

                if (! $response->successful()) {
                    return null;
                }

                return $response->json();
            } catch (\Throwable $e) {
                Log::warning('NLP service unavailable: ' . $e->getMessage());

                return null;
            }
        }

        return $this->classifier->classify($text);
    }

    /* Feeds a verified assessment into the learner so it keeps improving.
       Stores a labeled example locally (or posts to the external model). */
    public function train(array $payload): bool
    {
        if ($this->driver === 'external' && $this->url !== '') {
            try {
                $response = Http::timeout(5)->post($this->url . '/train', $payload);

                return $response->successful();
            } catch (\Throwable $e) {
                Log::warning('NLP training feed unavailable: ' . $e->getMessage());

                return false;
            }
        }

        return $this->classifier->train($payload);
    }

    public function trainingSize(): int
    {
        return $this->classifier->trainingSize();
    }
}