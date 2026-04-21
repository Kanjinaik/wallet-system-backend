<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use App\Http\Controllers\Api\RetailerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    private const STATUSES = ['open', 'in_progress', 'resolved', 'closed'];
    private const PRIORITIES = ['low', 'medium', 'high'];

    public function index(Request $request)
    {
        $user = $request->user();
        $query = SupportThread::with(['lastMessage', 'user'])
            ->when(!$this->isAdmin($user), fn ($q) => $q->where('user_id', $user->id));

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('issue_type', 'like', "%{$search}%");
            });
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }

        $threads = $query->orderByDesc('updated_at')->get();
        $threads = $threads->map(fn ($t) => $this->withFallbackFields($t));

        $summary = [
            'total' => $threads->count(),
            'open' => $threads->where('status', 'open')->count(),
            'resolved' => $threads->where('status', 'resolved')->count(),
        ];

        return response()->json([
            'threads' => $threads,
            'summary' => $summary,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'priority' => ['required', Rule::in(self::PRIORITIES)],
            'message' => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ]);

        $payload = [
            'user_id' => $request->user()->id,
            'issue_type' => $data['category'],
            'priority' => $data['priority'],
            'status' => 'open',
        ];
        if (Schema::hasColumn('support_threads', 'subject')) {
            $payload['subject'] = $data['subject'];
        }
        if (Schema::hasColumn('support_threads', 'category')) {
            $payload['category'] = $data['category'];
        }
        // auto-assign first admin to surface in admin inbox
        $adminId = User::where('role', 'admin')->value('id');
        if ($adminId && Schema::hasColumn('support_threads', 'admin_id')) {
            $payload['admin_id'] = $adminId;
        }

        $thread = SupportThread::create($payload);

        $fileUrl = $this->storeAttachment($request);

        SupportMessage::create([
            'support_thread_id' => $thread->id,
            'sender_type' => $this->normalizeSender($request->user()->role),
            'sender_id' => $request->user()->id,
            'message' => $data['message'],
            'file_url' => $fileUrl,
        ]);

        $thread = $this->withFallbackFields($thread);

        $this->notifyAdminOfNewTicket($thread);

        return response()->json($thread, 201);
    }

    public function messages(Request $request, SupportThread $thread)
    {
        $this->authorizeThread($request, $thread);
        $messages = $thread->messages()->orderBy('created_at')->get();
        return response()->json([
          'thread' => $this->withFallbackFields($thread),
          'messages' => $messages,
        ]);
    }

    public function sendMessage(Request $request)
    {
        $data = $request->validate([
            'ticket_id' => ['required', 'integer', 'exists:support_threads,id'],
            'message' => ['required_without:attachment', 'string', 'nullable'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ]);

        $thread = SupportThread::findOrFail($data['ticket_id']);
        $this->authorizeThread($request, $thread);

        $fileUrl = $this->storeAttachment($request);

        SupportMessage::create([
            'support_thread_id' => $thread->id,
            'sender_type' => $this->normalizeSender($request->user()->role),
            'sender_id' => $request->user()->id,
            'message' => $data['message'],
            'file_url' => $fileUrl,
        ]);

        if ($this->isAdmin($request->user())) {
            if (!$thread->admin_id) {
                $thread->admin_id = $request->user()->id;
            }
            if ($thread->status === 'open') {
                $thread->status = 'in_progress';
            }
        }
        $thread->updated_at = now();
        $thread->save();

        $title = $this->isAdmin($request->user()) ? 'Admin replied to your ticket' : 'New reply on your ticket';
        $this->notifyCounterparty($request->user(), $thread, $title);

        return response()->json(['message' => 'Message sent']);
    }

    public function updateStatus(Request $request)
    {
        $data = $request->validate([
            'ticket_id' => ['required', 'integer', 'exists:support_threads,id'],
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        $thread = SupportThread::findOrFail($data['ticket_id']);
        $this->authorizeThread($request, $thread);

        $thread->status = $data['status'];
        $thread->save();

        if ($data['status'] === 'resolved') {
            $this->notifyCounterparty($request->user(), $thread, 'Your issue is resolved');
        }

        return response()->json(['message' => 'Status updated']);
    }

    private function storeAttachment(Request $request): ?string
    {
        if (!$request->hasFile('attachment')) {
            return null;
        }
        $path = $request->file('attachment')->store('support-attachments', 'public');
        return Storage::disk('public')->url($path);
    }

    private function authorizeThread(Request $request, SupportThread $thread): void
    {
        $user = $request->user();
        if ($this->isAdmin($user)) {
          return;
        }
        if ($thread->user_id !== $user->id) {
            abort(403, 'Not allowed');
        }
    }

    private function isAdmin(User $user): bool
    {
        return $user->role === 'admin';
    }

    private function normalizeSender(string $role): string
    {
        return $role === 'user' ? 'retailer' : $role;
    }

    private function notifyAdminOfNewTicket(SupportThread $thread): void
    {
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            return;
        }
        RetailerController::notify(
            $admin->id,
            'support_ticket_created',
            'New support ticket',
            $thread->subject,
            ['ticket_id' => $thread->id]
        );
    }

    private function notifyCounterparty(User $sender, SupportThread $thread, string $title): void
    {
        $recipientId = $this->isAdmin($sender) ? $thread->user_id : ($thread->admin_id ?: User::where('role', 'admin')->value('id'));
        if (!$recipientId) {
            return;
        }
        RetailerController::notify(
            $recipientId,
            'support_ticket_update',
            $title,
            $thread->subject,
            ['ticket_id' => $thread->id, 'status' => $thread->status]
        );
    }

    private function withFallbackFields(SupportThread $thread): SupportThread
    {
        if (!$thread->subject) {
            $thread->subject = $thread->issue_type;
        }
        if (!$thread->category) {
            $thread->category = $thread->issue_type;
        }
        return $thread;
    }
}
