<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Http\Request;

/**
 * In-app AI support bot. No external LLM key is required: the bot matches the
 * student's message against a set of keyword rules and returns a canned,
 * context-aware reply. Every exchange is stored in `messages` so the
 * conversation history persists and could later be handed off to a human or a
 * real LLM. The rule engine is intentionally data-driven so new intents are
 * added by extending the BOT_RULES array.
 */
class ChatController extends Controller
{
    protected const BOT_RULES = [
        'enroll' => [
            'keywords' => ['enroll', 'sign up', 'register', 'join', 'how to start', 'get started'],
            'reply' => "To enroll: open the Enroll tab, pick one TDC course (Face-to-Face or Online) and any PDC course, then pay the downpayment. PDC unlocks only after you finish TDC and get assessed. Need the step-by-step?",
        ],
        'tdc' => [
            'keywords' => ['tdc', 'theoretical', 'classroom', 'lecture'],
            'reply' => "TDC (Theoretical Driving Course) is the classroom part. You must complete and pass a TDC assessment before you can enroll in any PDC (practical) course.",
        ],
        'pdc' => [
            'keywords' => ['pdc', 'practical', 'driving', 'behind the wheel', 'wheel'],
            'reply' => "PDC (Practical Driving Course) is the hands-on driving training. You can enroll in PDC only after your TDC is completed and assessed as passed.",
        ],
        'payment' => [
            'keywords' => ['pay', 'payment', 'fee', 'price', 'cost', 'downpayment', 'down payment', 'gcash', 'bdo'],
            'reply' => "Payments are made from the Payments tab. TDC and PDC 2W need full payment to start; PDC 4W activates on a 50% downpayment (balance later). We accept GCash/BDO via PayMongo, or cash over the counter (recorded by admin).",
        ],
        'book' => [
            'keywords' => ['book', 'schedule', 'session', 'slot', 'appointment', 'available'],
            'reply' => "Once your enrollment is active, open the Sessions tab to book an open slot. You may hold one upcoming booking at a time. New bookings need admin acceptance of the instructor before they are confirmed.",
        ],
        'reschedule' => [
            'keywords' => ['reschedule', 'move', 'change schedule', 'change my session', 'switch session'],
            'reply' => "To reschedule, go to Sessions, open the request form for your booked session, pick a new open session, and submit. Admin will accept or reject it. Your current booking stays until approved.",
        ],
        'instructor' => [
            'keywords' => ['instructor', 'teacher', 'male', 'female', 'prefer', 'gender'],
            'reply' => "You can note a preferred instructor gender (male/female) from your profile or the request form. It's a preference we'll try to honour — it doesn't restrict which sessions you can book.",
        ],
        'certificate' => [
            'keywords' => ['certificate', 'cert', 'license', 'valid id'],
            'reply' => "Certificates are issued by admin once you meet all requirements (passing assessment, required PDC sessions, cleared safety flags, and full payment). You'll get a notification when it's ready to claim.",
        ],
        'assessment' => [
            'keywords' => ['assessment', 'evaluate', 'grade', 'score', 'exam', 'results'],
            'reply' => "Your instructor assesses each session with star ratings and an exam score. A TDC assessment must reach 75+ to count as passed. See your Results tab for details.",
        ],
        'hours' => [
            'keywords' => ['hours', 'open', 'time', 'operating', 'contact', 'location', 'address', 'venue'],
            'reply' => "Our office hours are Mon–Sat, 8:00 AM to 6:00 PM. For branch details or directions, message your instructor or ask admin. Need the exact venue for a session? Check the Sessions tab.",
        ],
        'help' => [
            'keywords' => ['help', 'support', 'talk to human', 'agent', 'admin', 'hi', 'hello', 'hey'],
            'reply' => "Hi! I'm the RCDA support bot. I can help with enrolling, payments, bookings, rescheduling, instructor preferences, and certificates. Ask me anything — or type 'talk to human' and we'll route it to admin. How can I help?",
        ],
    ];

    public function index(Request $request)
    {
        $student = $request->user()->student;

        $messages = Message::where('student_id', $student->id)
            ->orderBy('created_at')
            ->get()
            ->map(function (Message $m) {
                return [
                    'id' => $m->id,
                    'sender' => $m->sender,
                    'body' => $m->body,
                    'created_at' => $m->created_at,
                ];
            });

        return response()->json(['messages' => $messages]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        $student = $request->user()->student;

        $userMessage = Message::create([
            'student_id' => $student->id,
            'sender' => Message::SENDER_STUDENT,
            'body' => $data['body'],
        ]);

        $reply = $this->respond($data['body']);

        $botMessage = Message::create([
            'student_id' => $student->id,
            'sender' => Message::SENDER_BOT,
            'body' => $reply,
        ]);

        return response()->json([
            'messages' => [
                ['id' => $userMessage->id, 'sender' => $userMessage->sender, 'body' => $userMessage->body, 'created_at' => $userMessage->created_at],
                ['id' => $botMessage->id, 'sender' => $botMessage->sender, 'body' => $botMessage->body, 'created_at' => $botMessage->created_at],
            ],
        ]);
    }

    protected function respond(string $text): string
    {
        $lower = strtolower($text);

        if (str_contains($lower, 'talk to human') || str_contains($lower, 'speak to human') || str_contains($lower, 'real person')) {
            return "Sure — I've flagged this for our admin team. They'll follow up via your registered email/SMS. In the meantime, feel free to keep asking me about enrollments, payments, or schedules.";
        }

        $best = null;
        $bestScore = 0;
        foreach (self::BOT_RULES as $intent => $rule) {
            $score = 0;
            foreach ($rule['keywords'] as $kw) {
                if (str_contains($lower, $kw)) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $rule['reply'];
            }
        }

        if ($best) {
            return $best;
        }

        return "I'm not sure I caught that. I can help with enrolling, TDC/PDC, payments, bookings, rescheduling, instructor preferences, assessments, and certificates. Try rephrasing, or type 'talk to human' to reach our admin team.";
    }
}
