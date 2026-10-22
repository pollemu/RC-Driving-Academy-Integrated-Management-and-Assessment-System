<?php

namespace Tests\Feature;

use App\Services\PayMongoService;
use Tests\TestCase;

class PayMongoWebhookTest extends TestCase
{
    private function signature(string $secret, string $payload, string $key = 'te'): string
    {
        $t = (string) time();
        $hash = hash_hmac('sha256', $t . '.' . $payload, $secret);

        // PayMongo sends t=<ts>,te=<test hash>,li=<live hash> and leaves the
        // hash for the other mode empty.
        if ($key === 'li') {
            return "t={$t},te=,li={$hash}";
        }

        return "t={$t},te={$hash},li=";
    }

    public function test_signature_is_rejected_without_a_configured_secret(): void
    {
        config(['services.paymongo.webhook_secret' => '']);
        $payload = '{"data":{}}';

        $this->assertFalse((new PayMongoService())->verifyWebhookSignature($payload, 't=123,te=abc,li='));
    }

    public function test_signature_is_accepted_with_a_single_secret(): void
    {
        config(['services.paymongo.webhook_secret' => 'secret-one']);
        $payload = '{"data":{"attributes":{"type":"checkout_session.payment.paid"}}}';
        $service = new PayMongoService();

        $this->assertTrue($service->verifyWebhookSignature($payload, $this->signature('secret-one', $payload)));
    }

    public function test_signature_is_accepted_with_any_of_multiple_comma_separated_secrets(): void
    {
        // PayMongo issues a separate signing secret per endpoint (test vs live).
        config(['services.paymongo.webhook_secret' => 'test-secret, live-secret']);
        $payload = '{"data":{"attributes":{"type":"checkout_session.payment.paid"}}}';
        $service = new PayMongoService();

        $this->assertTrue($service->verifyWebhookSignature($payload, $this->signature('test-secret', $payload)));
        $this->assertTrue($service->verifyWebhookSignature($payload, $this->signature('live-secret', $payload)));
        $this->assertTrue($service->verifyWebhookSignature($payload, $this->signature('live-secret', $payload, 'li')));
    }

    public function test_signature_is_rejected_for_an_unknown_secret(): void
    {
        config(['services.paymongo.webhook_secret' => 'test-secret,live-secret']);
        $payload = '{"data":{}}';
        $service = new PayMongoService();

        $this->assertFalse($service->verifyWebhookSignature($payload, $this->signature('wrong-secret', $payload)));
    }

    public function test_webhook_endpoint_rejects_an_unsigned_request(): void
    {
        $this->postJson('/api/webhooks/paymongo', ['data' => []])
            ->assertStatus(401);
    }

    public function test_webhook_endpoint_accepts_a_signed_non_paid_event(): void
    {
        config(['services.paymongo.webhook_secret' => 'secret-one']);
        $payload = '{"data":{"attributes":{"type":"checkout_session.payment.failed","data":{}}}}';

        $this->call('POST', '/api/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => $this->signature('secret-one', $payload),
        ], $payload)->assertOk();
    }
}
