<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PayMongoService;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    /* Public PayMongo webhook. PayMongo POSTs events here (needs a public HTTPS
       URL in production). The signature is verified before anything else, and a
       payment is only activated when PayMongo confirms it was actually paid for
       at least the amount we expected. */
    public function paymongo(Request $request)
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Paymongo-Signature');

        $paymongo = new PayMongoService();

        if (! $paymongo->verifyWebhookSignature($payload, $signature)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $root = json_decode($payload, true) ?: [];

        // PayMongo wraps events as data.attributes.{type,data} but some
        // payloads nest it as data.{type,data}; support both shapes.
        $attributes = $root['data']['attributes'] ?? $root['data'] ?? [];
        $type = $attributes['type'] ?? '';
        $resource = $attributes['data'] ?? null;

        $paidEvents = ['checkout_session.payment.paid', 'checkout_session.paid', 'payment.paid'];

        if (! in_array($type, $paidEvents, true) || ! is_array($resource)) {
            return response()->json(['received' => true]);
        }

        // Our payments store the Checkout Session id in paymongo_id.
        $sessionId = $resource['id'] ?? null;
        if (! $sessionId) {
            return response()->json(['received' => true]);
        }

        $payments = Payment::where('paymongo_id', $sessionId)
            ->where('status', 'pending')
            ->get();

        if ($payments->isEmpty()) {
            return response()->json(['received' => true, 'verified' => 0]);
        }

        $info = $paymongo->summarize($resource);
        if (! $info || empty($info['paid'])) {
            return response()->json(['received' => true, 'verified' => 0]);
        }

        // Refuse to activate a partial or tampered payment.
        $expected = (int) round($payments->sum('amount') * 100);
        if ($info['paid_amount_centavos'] > 0 && $info['paid_amount_centavos'] < $expected) {
            return response()->json(['received' => true, 'verified' => 0, 'reason' => 'amount_mismatch']);
        }

        foreach ($payments as $payment) {
            $payment->markVerified(null); // system-verified via webhook
        }

        return response()->json(['received' => true, 'verified' => $payments->count()]);
    }
}
