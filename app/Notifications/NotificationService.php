<?php

namespace App\Notifications;

use App\Models\Student;
use App\Models\User;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\Channels\MapsChannel;
use App\Notifications\Channels\SemaphoreSmsChannel;

/**
 * Central notification dispatcher for RC Driving Academy.
 *
 * Every public method composes a human-readable message and fans it out to
 * the configured channels. Channels degrade gracefully: if no provider keys
 * are present (or the channel is disabled) the attempt is recorded in
 * notification_logs rather than raising an error, so the whole app works
 * without external credentials — keys are wired in later via .env.
 */
class NotificationService
{
    protected SemaphoreSmsChannel $sms;
    protected EmailChannel $mail;
    protected MapsChannel $maps;

    public function __construct()
    {
        $this->sms = new SemaphoreSmsChannel();
        $this->mail = new EmailChannel();
        $this->maps = new MapsChannel();
    }

    protected function phone(?Student $student): ?string
    {
        if (! $student || ! $student->phone) {
            return null;
        }
        // Normalize PH mobile numbers to international format for Semaphore.
        $p = preg_replace('/[^0-9]/', '', $student->phone);
        if (str_starts_with($p, '0')) {
            $p = '63' . substr($p, 1);
        }
        return $p;
    }

    public function registration(User $user): void
    {
        $name = $user->name;
        $smsText = "Welcome to RC Driving Academy, $name! Your student account is ready. Log in to enroll in TDC/PDC and book your sessions. -RCDA";
        $emailSubject = 'Welcome to RC Driving Academy';
        $emailBody = "Hi $name,\n\nWelcome to RC Driving Academy! Your student account has been created.\n\nYou can now enroll in our Theoretical Driving Course (TDC) and Practical Driving Course (PDC), and book your driving sessions online.\n\nSee you on the road!\nRC Driving Academy";

        if ($user->student) {
            $to = $this->phone($user->student);
            if ($to) {
                $this->sms->send($to, $smsText, 'registration', $user->id);
            }
        }
        $this->mail->send($user->email, $emailSubject, $emailBody, 'registration', $user->id);
    }

    public function paymentVerified(Student $student, string $courseName, float $amount): void
    {
        $name = $student->user->name;
        $amt = number_format($amount, 2);
        $smsText = "Hi $name, we received your payment of ₱$amt for $courseName. Your enrollment is now active — you may book your sessions. -RCDA";
        $emailSubject = "Payment received — $courseName";
        $emailBody = "Hi $name,\n\nWe have received and verified your payment of ₱$amt for $courseName.\n\nYour enrollment is now active. You can book your driving sessions from your student dashboard.\n\nThank you,\nRC Driving Academy";

        $to = $this->phone($student);
        if ($to) {
            $this->sms->send($to, $smsText, 'payment_verified', $student->user_id);
        }
        $this->mail->send($student->user->email, $emailSubject, $emailBody, 'payment_verified', $student->user_id);
    }

    public function certificateIssued(Student $student, string $courseName, string $certificateNo): void
    {
        $name = $student->user->name;
        $smsText = "Congrats $name! Your $courseName certificate ($certificateNo) is issued. Present this ID to claim your printed copy. -RCDA";
        $emailSubject = "Your $courseName certificate is ready";
        $emailBody = "Hi $name,\n\nCongratulations! Your $courseName certificate has been issued.\n\nCertificate ID: $certificateNo\n\nYou may present this ID to claim your printed certificate at the academy.\n\nDrive safe,\nRC Driving Academy";

        $to = $this->phone($student);
        if ($to) {
            $this->sms->send($to, $smsText, 'certificate_issued', $student->user_id);
        }
        $this->mail->send($student->user->email, $emailSubject, $emailBody, 'certificate_issued', $student->user_id);
    }

    /**
     * Session reminder. When MAPS is enabled, include a route link to the
     * venue. Returns the maps URL (or null) so the caller can surface it.
     */
    public function sessionReminder(Student $student, string $courseName, string $venue, string $when): ?string
    {
        $name = $student->user->name;
        $smsText = "Reminder $name: your $courseName session is on $when at $venue. -RCDA";
        $emailSubject = "Driving session reminder — $courseName";
        $emailBody = "Hi $name,\n\nThis is a reminder for your upcoming $courseName session:\n\nWhen: $when\nVenue: $venue\n\nSee you there!\nRC Driving Academy";

        $mapsUrl = $this->maps->directionsUrl($venue);
        $this->maps->log('reminder', $venue, $mapsUrl, $student->user_id);

        $to = $this->phone($student);
        if ($to) {
            $this->sms->send($to, $smsText, 'reminder', $student->user_id);
        }
        $this->mail->send($student->user->email, $emailSubject, $emailBody, 'reminder', $student->user_id);

        return $mapsUrl;
    }

    public function maps(): MapsChannel
    {
        return $this->maps;
    }
}
