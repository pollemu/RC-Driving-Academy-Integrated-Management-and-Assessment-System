<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PayMongoService
{
    protected string $secret;
    protected string $public;
    protected string $base;

    public function __construct()
    {
        $this->secret = config('services.paymongo.secret') ?: env('PAYMONGO_SECRET_KEY');
        $this->public = config('services.paymongo.public') ?: env('PAYMONGO_PUBLIC_KEY');
        $this->base = rtrim(env('PAYMONGO_API_URL', 'https://api.paymongo.com/v1'), '/');
    }

    public function createPaymentIntent(int $amountCentavos, string $description, string $reference): ?array
    {
        $response = Http::withBasicAuth($this->secret, '')
            ->post($this->base . '/payment_intents', [
                'data' => [
                    'attributes' => [
                        'amount' => $amountCentavos,
                        'payment_method_allowed' => ['gcash', 'bdo'],
                        'currency' => 'PHP',
                        'description' => $description,
                        'statement_descriptor' => 'RC DRIVING',
                        'metadata' => ['reference' => $reference],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json('data');
    }

    public function createPaymentIntentWithCheckout(int $amountCentavos, string $description, string $reference, string $successUrl, string $cancelUrl): ?string
    {
        $session = $this->createCheckoutSession($amountCentavos, $description, $reference, $successUrl, $cancelUrl);

        return $session ? $session['url'] : null;
    }

    /* Creates a PayMongo Checkout Session and returns ['id' => sessionId, 'url' => hostedCheckoutUrl].
       The student is redirected to `url` (GCash / Card / OTC) and PayMongo appends
       ?checkout_session_id=...&payment_id=... to the success URL on completion. */
    public function createCheckoutSession(int $amountCentavos, string $description, string $reference, string $successUrl, string $cancelUrl): ?array
    {
        $intent = $this->createPaymentIntent($amountCentavos, $description, $reference);

        if (! $intent) {
            return null;
        }

        $response = Http::withBasicAuth($this->secret, '')
            ->post($this->base . '/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'payment_intent_id' => $intent['id'],
                        'success_url' => $successUrl,
                        'cancel_url' => $cancelUrl,
                        'description' => $description,
                        'line_items' => [
                            [
                                'name' => $description,
                                'amount' => $amountCentavos,
                                'currency' => 'PHP',
                                'quantity' => 1,
                            ],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json('data');

        return [
            'id' => $data['id'] ?? null,
            'url' => $data['attributes']['checkout_url'] ?? null,
        ];
    }

    /* Retrieves a Checkout Session. Returns ['id','payment_id'(null until paid),'status'] or null. */
    public function getCheckoutSession(string $sessionId): ?array
    {
        $response = Http::withBasicAuth($this->secret, '')
            ->get($this->base . '/checkout_sessions/' . $sessionId);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json('data');
        $attrs = $data['attributes'] ?? [];

        return [
            'id' => $data['id'] ?? $sessionId,
            'payment_id' => $attrs['payment_id'] ?? null,
            'status' => $attrs['status'] ?? null,
        ];
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = env('PAYMONGO_WEBHOOK_SECRET');
        if (! $secret) {
            return false;
        }
        $computed = hash_hmac('sha256', $payload, $secret);

        return hash_equals($computed, $signature);
    }
}
