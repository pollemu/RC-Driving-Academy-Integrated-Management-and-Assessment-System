<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\Channels\SemaphoreSmsChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Self-service password reset — the "pick Gmail or SMS" flow.
 *
 * Step 1  POST /password/forgot  { channel: email|sms, email?|phone? }
 *         Finds the account, generates a 6-digit code (hashed, 15-minute
 *         expiry) and delivers it over the chosen channel — Gmail through
 *         the Brevo/SMTP mailer, SMS through Semaphore. Both channels are
 *         already configured in .env; each degrades gracefully to a 503
 *         with a clear message when its provider is unavailable.
 *
 * Step 2  POST /password/reset   { channel, email?|phone?, code, password }
 *         Verifies the code and sets the new password.
 *
 * The reset code reuses the users table's email_verification_code /
 * email_verification_expires_at columns (hashed + expiring), which the
 * email-verification flow no longer needs once verified.
 */
class PasswordResetController extends Controller
{
    public function request(Request $request)
    {
        $data = $request->validate([
            'channel' => ['required', 'in:email,sms'],
            'email' => ['nullable', 'required_if:channel,email', 'email'],
            'phone' => ['nullable', 'required_if:channel,sms', 'string'],
        ]);

        if ($data['channel'] === 'email') {
            $user = User::where('email', $data['email'])->first();

            if (! $user) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No account uses that email address. Check for typos or switch to SMS.',
                ], 404);
            }
        } else {
            $user = $this->findByPhone($data['phone']);

            if (! $user) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No account has that phone number on file. Use the Gmail option instead.',
                ], 404);
            }
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->forceFill([
            'email_verification_code' => Hash::make($code),
            'email_verification_expires_at' => now()->addMinutes(15),
        ])->save();

        $masked = $data['channel'] === 'email' ? $this->maskEmail($user->email) : $this->maskPhone($this->phoneOf($user));

        $sent = $data['channel'] === 'email'
            ? $this->sendEmailCode($user, $code, $masked)
            : $this->sendSmsCode($user, $code, $masked);

        if (! $sent) {
            return response()->json([
                'ok' => false,
                'message' => 'The delivery provider did not accept the message. Try the other option, or ask the admin to reset your password.',
            ], 503);
        }

        $name = $data['channel'] === 'email'
            ? ($this->isGmail($user->email) ? 'your Gmail' : 'your email')
            : 'SMS';

        return response()->json([
            'ok' => true,
            'message' => "We sent a 6-digit code to $masked via $name. It expires in 15 minutes.",
            'contact' => $masked,
            'via' => $name,
        ]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'channel' => ['required', 'in:email,sms'],
            'email' => ['nullable', 'required_if:channel,email', 'email'],
            'phone' => ['nullable', 'required_if:channel,sms', 'string'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($data['channel'] === 'email') {
            $user = User::where('email', $data['email'])->first();
            if (! $user) {
                return response()->json(['ok' => false, 'message' => 'No account uses that email address.'], 404);
            }
        } else {
            $user = $this->findByPhone($data['phone']);
            if (! $user) {
                return response()->json(['ok' => false, 'message' => 'No account has that phone number on file.'], 404);
            }
        }

        if (! $user->email_verification_code || ! $user->email_verification_expires_at || $user->email_verification_expires_at->isPast()) {
            return response()->json(['ok' => false, 'message' => 'This code has expired. Request a new one.'], 422);
        }

        if (! Hash::check($data['code'], $user->email_verification_code)) {
            return response()->json(['ok' => false, 'message' => 'That code is incorrect.'], 422);
        }

        $user->forceFill([
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
        ])->save();
        $user->update(['password' => Hash::make($data['password'])]);

        // Invalidate any other active sessions (Sanctum tokens).
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        return response()->json(['ok' => true, 'message' => 'Password updated. You can now sign in with your new password.']);
    }

    /* ---- Helpers ---- */

    private function findByPhone(?string $phone): ?User
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }
        $suffix = strlen($digits) > 10 ? substr($digits, -10) : $digits;

        return User::where(function ($q) use ($suffix) {
            $q->whereHas('student', fn ($s) => $s->where('phone', 'like', "%$suffix"))
                ->orWhereHas('instructor', fn ($i) => $i->where('phone', 'like', "%$suffix"));
        })->first();
    }

    /** The stored phone digits for a user (student or instructor). */
    private function phoneOf(User $user): string
    {
        return (string) ($user->student?->phone ?? $user->instructor?->phone ?? '');
    }

    private function normalizedPhone(User $user): string
    {
        $p = preg_replace('/[^0-9]/', '', $this->phoneOf($user));
        if (str_starts_with($p, '0')) {
            $p = '63' . substr($p, 1);
        }

        return $p;
    }

    private function sendEmailCode(User $user, string $code, string $masked): bool
    {
        $subject = 'RC Driving Academy — Password Reset Code';
        $body = "Hi {$user->name},\n\n"
            . "Your password reset code is: $code\n\n"
            . "It expires in 15 minutes. If you did not request this, you can safely ignore this email.\n\n"
            . "RC Driving Academy";

        return (new EmailChannel())->send($user->email, $subject, $body, 'password_reset', $user->id);
    }

    private function sendSmsCode(User $user, string $code, string $masked): bool
    {
        $message = "RCDA password reset code: $code (valid 15 minutes). Do not share this code. -RC Driving Academy";

        return (new SemaphoreSmsChannel())->send($this->normalizedPhone($user), $message, 'password_reset', $user->id);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1) . '***@' . $domain;
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone);
        if (strlen($digits) < 4) {
            return $digits === '' ? '—' : str_repeat('*', strlen($digits));
        }

        return str_repeat('*', strlen($digits) - 4) . substr($digits, -4);
    }

    private function isGmail(string $email): bool
    {
        return str_ends_with(mb_strtolower($email), '@gmail.com');
    }
}
