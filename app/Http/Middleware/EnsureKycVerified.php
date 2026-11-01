<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

/* Blocks booking/enrollment/payment endpoints for a student whose identity
   (KYC) has not been verified yet. Browsing stays open — the dashboard is
   visible and read-only endpoints work; only new money/commitment actions
   (cart enrollment, payments, PDC session booking, booking requests) stop
   until an admin approves the submitted ID + selfie.

   Responds with a machine-readable shape so the frontend can prompt the
   student: 403 { message, requires_kyc: true }. */
class EnsureKycVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Only students have KYC walls; admins/instructors pass freely.
        if (! $user || ! $user->isStudent() || $user->hasVerifiedKyc()) {
            return $next($request);
        }

        $message = match ($user->kyc_status) {
            User::KYC_PENDING => 'Your identity documents are being reviewed. Booking unlocks as soon as an admin verifies them.',
            User::KYC_REJECTED => 'Your identity verification was rejected: ' . ($user->kyc_rejection_reason ?: 'please resubmit your documents') . '.',
            default => 'Complete identity verification (upload your valid ID and a selfie) to book lessons and make payments.',
        };

        return response()->json([
            'message' => $message,
            'requires_kyc' => true,
        ], 403);
    }
}
