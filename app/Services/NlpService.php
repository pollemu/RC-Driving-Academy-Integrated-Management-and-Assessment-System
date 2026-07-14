<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NlpService
{
    protected string $url;

    public function __construct()
    {
        $this->url = rtrim(env('NLP_SERVICE_URL', 'http://localhost:5000'), '/');
    }

    public function classify(string $text): ?array
    {
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
}
