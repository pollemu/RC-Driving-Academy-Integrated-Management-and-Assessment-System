<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PayMongoService;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    /* Public PayMongo webhook. PayMongo POSTs events here (needs a public
       HTTPS URL in production). Signature is verified before anything else. */
    public function paymongo(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('payload-signature')
            ?? $request->header('Payload-Signature')
            ?? '';

        if (! (new PayMongoService())->verifyWebhookSignature($payload, $signature)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $event = json_decode($payload, true);
        $type = $event['type'] ?? '';
        $sessionId = $event['data']['id'] ?? null;

        if (! in_array($type, ['checkout_session.paid', 'payment.paid'], true)) {
            return response()->json(['received' => true]);
        }

        if (! $sessionId) {
            return response()->json(['received' => true]);
        }

        $payments = Payment::where('paymongo_id', $sessionId)
            ->where('status', 'pending')
            ->get();

        foreach ($payments as $payment) {
            $payment->markVerified(null); // system-verified via webhook
        }

        return response()->json(['received' => true, 'verified' => $payments->count()]);
    }
}
