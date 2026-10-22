<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PayMongoService
{
    protected string $secret;
    protected string $public;
    protected string $base;
    protected int $timeout;

    public function __construct()
    {
        $this->secret = (string) config('services.paymongo.secret');
        $this->public = (string) config('services.paymongo.public');
        $this->base = rtrim((string) config('services.paymongo.api_url', 'https://api.paymongo.com/v1'), '/');
        $this->timeout = (int) config('services.paymongo.timeout', 20);
    }

    /* True only when a real (non-placeholder) secret key is configured. */
    public function isConfigured(): bool
    {
        return $this->secret !== ''
            && ! str_contains($this->secret, 'xxxx')
            && str_starts_with($this->secret, 'sk_');
    }

    /* Payment methods the academy accepts. Configurable so the client can match
       whatever is enabled on their PayMongo account (see PAYMONGO_PAYMENT_METHODS).
       Valid values: card, gcash, grab_pay, paymaya, billease, dob, qrph. */
    protected function paymentMethods(?array $default = null): array
    {
        $configured = trim((string) config('services.paymongo.payment_methods', ''));
        if ($configured !== '') {
            $methods = array_values(array_filter(array_map('trim', explode(',', $configured))));
            if ($methods) {
                return $methods;
            }
        }

        // Gateways available to every account type (no business registration needed).
        return $default ?? ['qrph', 'gcash', 'card'];
    }

    protected function client()
    {
        return Http::withBasicAuth($this->secret, '')
            ->acceptJson()
            ->timeout($this->timeout);
    }

    /* Creates a hosted Checkout Session (PayMongo also creates the backing
       Payment Intent). Returns ['id' => cs_..., 'url' => hostedCheckoutUrl] or null. */
    public function createCheckoutSession(int $amountCentavos, string $description, string $reference, string $successUrl, string $cancelUrl, ?array $methods = null): ?array
    {
        $response = $this->client()
            ->post($this->base . '/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'payment_method_types' => $this->paymentMethods($methods),
                        'line_items' => [
                            [
                                'name' => $description,
                                'amount' => $amountCentavos,
                                'currency' => 'PHP',
                                'quantity' => 1,
                            ],
                        ],
                        'success_url' => $successUrl,
                        'cancel_url' => $cancelUrl,
                        'description' => $description,
                        'reference_number' => $reference,
                        'metadata' => ['reference' => $reference],
                        'send_email_receipt' => false,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            $detail = $response->json('errors.0.detail') ?? $response->json('errors.0.code');
            report(new \RuntimeException('PayMongo checkout creation failed ('.$response->status().'): '.($detail ?: 'unknown error')));

            return null;
        }

        $data = $response->json('data');

        if (empty($data['id']) || empty($data['attributes']['checkout_url'])) {
            return null;
        }

        return [
            'id' => $data['id'],
            'url' => $data['attributes']['checkout_url'],
        ];
    }

    /* Retrieves and normalises a Checkout Session. Returns null on failure,
       otherwise: id, status, paid (bool), paid_amount_centavos (int), payment_id.
       `paid` is true only when PayMongo actually recorded a paid payment or the
       backing payment intent succeeded — never inferred from the session alone. */
    public function getCheckoutSession(string $sessionId): ?array
    {
        $response = $this->client()
            ->get($this->base . '/checkout_sessions/' . urlencode($sessionId));

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json('data');

        return $this->summarize($data);
    }

    /* Normalises a checkout-session payload (from the API or a webhook) into the
       fields callers need. Shared so webhook and redirect paths verify identically. */
    public function summarize(?array $data): ?array
    {
        if (! is_array($data) || empty($data['id'])) {
            return null;
        }

        $attrs = $data['attributes'] ?? [];

        $paidAmount = 0;
        $paid = false;
        $paymentId = null;

        foreach ((array) ($attrs['payments'] ?? []) as $payment) {
            $pAttrs = $payment['attributes'] ?? [];
            if (($pAttrs['status'] ?? null) === 'paid') {
                $paid = true;
                $paymentId = $payment['id'] ?? ($pAttrs['id'] ?? null);
                $paidAmount += (int) ($pAttrs['amount'] ?? 0);
            }
        }

        $intentStatus = $attrs['payment_intent']['attributes']['status'] ?? ($attrs['payment_intent']['status'] ?? null);

        return [
            'id' => $data['id'],
            'status' => $attrs['status'] ?? null,
            'paid' => $paid || $intentStatus === 'succeeded',
            'paid_amount_centavos' => $paidAmount,
            'payment_intent_status' => $intentStatus,
            'payment_id' => $paymentId ?? ($attrs['payment_id'] ?? null),
        ];
    }

    /*
     * Verifies a PayMongo webhook signature.
     *
     * PayMongo sends the header "Paymongo-Signature" formatted as:
     *   t=<unix-timestamp>,te=<test-hash>,li=<live-hash>
     * where each hash is HMAC-SHA256 of "<timestamp>.<raw-body>" keyed by the
     * webhook secret. We accept either the test (te) or live (li) hash, and we
     * reject stale timestamps to blunt replay of captured payloads.
     *
     * PayMongo scopes endpoints by mode (test vs live) and gives EACH endpoint
     * its own signing secret, so PAYMONGO_WEBHOOK_SECRET may hold a
     * comma-separated list of secrets (e.g. the test one and the live one).
     * Any one of them may match.
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader): bool
    {
        $configured = (string) config('services.paymongo.webhook_secret');
        $secrets = array_values(array_filter(array_map('trim', explode(',', $configured))));
        if (empty($secrets) || str_contains($configured, 'xxxx') || trim($signatureHeader) === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $chunk) {
            $pair = explode('=', trim($chunk), 2);
            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }

        $timestamp = $parts['t'] ?? null;
        if (! $timestamp || ! ctype_digit((string) $timestamp)) {
            return false;
        }

        // Optional replay window. Disabled by default (0) so a genuinely-paid
        // but delayed/retried webhook is never dropped; the handler is
        // idempotent and amount-checked, so replay adds no real exposure.
        $tolerance = (int) config('services.paymongo.webhook_tolerance', 0);
        if ($tolerance > 0 && abs(time() - (int) $timestamp) > $tolerance) {
            return false;
        }

        foreach ($secrets as $secret) {
            $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

            foreach (['te', 'li'] as $key) {
                if (isset($parts[$key]) && hash_equals($expected, $parts[$key])) {
                    return true;
                }
            }
        }

        return false;
    }
}
