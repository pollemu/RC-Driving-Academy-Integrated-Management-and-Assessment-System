<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Services\PayMongoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function paymongo(Request $request): JsonResponse
    {
        $signature = $request->header('Paymongo-Signature');
        $payload = $request->getContent();

        $paymongo = new PayMongoService();
        if (! $paymongo->verifyWebhookSignature($payload, (string) $signature)) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $event = $request->input('type');
        $attributes = $request->input('data.attributes', []);

        if (! in_array($event, ['payment.paid', 'payment_intent.paid', 'payment_intent.succeeded'])) {
            return response()->json(['status' => 'ignored']);
        }

        $intentId = $attributes['id'] ?? null;
        $metadata = $attributes['metadata'] ?? [];
        $reference = $metadata['reference'] ?? $intentId;

        $payment = Payment::where('paymongo_id', $reference)->first();

        if (! $payment) {
            return response()->json(['status' => 'no matching payment']);
        }

        if ($payment->status !== 'verified') {
            $payment->update([
                'status' => 'verified',
                'verified_by' => null,
                'verified_at' => now(),
            ]);

            $enrollment = Enrollment::firstOrNew([
                'student_id' => $payment->student_id,
                'course_id' => $payment->course_id,
            ]);
            $enrollment->status = 'active';
            $enrollment->enrolled_at = $enrollment->enrolled_at ?? now();
            $enrollment->save();
        }

        return response()->json(['status' => 'ok']);
    }
}
