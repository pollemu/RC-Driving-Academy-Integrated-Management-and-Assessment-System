<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Message;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/* Admin-side human chat. When a student asks the bot to "talk to a human" (or
   whenever the admin wants to answer a student), the conversation shows up
   here as one thread per student. Replying leaves a message authored by the
   logged-in admin directly in the student's chat widget, and clears the
   student's pending chat-escalation feed entry. */

class ChatController extends Controller
{
    /* One row per student that has ever messaged the chat. */
    public function index(Request $request)
    {
        $studentIds = Message::whereNotNull('student_id')
            ->distinct()
            ->pluck('student_id');

        $students = Student::with('user')
            ->whereIn('id', $studentIds)
            ->orderBy('id')
            ->get()
            ->map(function (Student $s) {
                $last = Message::where('student_id', $s->id)->orderByDesc('id')->first();

                /* Student messages that arrived after the newest admin reply
                   (or ever, if the admin has not answered yet). */
                $lastAdminId = (int) (Message::where('student_id', $s->id)
                    ->where('sender', Message::SENDER_ADMIN)
                    ->orderByDesc('id')
                    ->value('id') ?? 0);

                $unread = Message::where('student_id', $s->id)
                    ->where('sender', Message::SENDER_STUDENT)
                    ->where('id', '>', $lastAdminId)
                    ->count();

                return [
                    'student_id' => $s->id,
                    'name' => $s->user->name ?? 'Student',
                    'avatar' => $s->user->avatar_path,
                    'last_message' => $last ? [
                        'sender' => $last->sender,
                        'body' => Str::before($last->body, "\n"),
                        'created_at' => (string) $last->created_at,
                    ] : null,
                    'unread' => $unread,
                    /* True when the bot asked for a human and nobody answered yet. */
                    'needs_human' => $unread > 0 && ! Message::adminReplied($s),
                ];
            });

        return response()->json(['threads' => $students]);
    }

    /* Full thread for one student, oldest first. */
    public function show(Student $student)
    {
        $messages = Message::where('student_id', $student->id)
            ->orderBy('id')
            ->get()
            ->map(fn (Message $m) => [
                'id' => $m->id,
                'sender' => $m->sender,
                'body' => $m->body,
                'created_at' => $m->created_at,
            ]);

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $student->user->name ?? 'Student',
            ],
            'messages' => $messages,
        ]);
    }

    /* The admin answers inside the same thread the student already has — the
       reply lands in their chat widget as an "admin" bubble. */
    public function reply(Request $request, Student $student)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        $message = Message::create([
            'student_id' => $student->id,
            'sender' => Message::SENDER_ADMIN,
            'admin_id' => auth()->id(),
            'body' => $data['body'],
        ]);

        /* Answered, so the "wants a human" feed entry no longer blinks. */
        try {
            AdminNotification::where('category', AdminNotification::CATEGORY_CHAT)
                ->whereNull('read_at')
                ->where('user_id', $student->user_id)
                ->get()
                ->each(fn (AdminNotification $n) => $n->markRead());
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['message' => [
            'id' => $message->id,
            'sender' => $message->sender,
            'body' => $message->body,
            'created_at' => $message->created_at,
        ]]);
    }
}
