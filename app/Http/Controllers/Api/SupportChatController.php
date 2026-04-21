<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\RetailerController;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportChatController extends Controller
{
    public function retailerIndex(Request $request)
    {
        $threads = SupportThread::with(['lastMessage', 'admin'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get();

        return response()->json($threads);
    }

    public function adminIndex()
    {
        $threads = SupportThread::with(['lastMessage', 'user'])
            ->orderByDesc('updated_at')
            ->get();

        return response()->json($threads);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'issue_type' => ['required', 'string', 'max:255'],
            'priority'   => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'tx_id'      => ['nullable', 'string', 'max:255'],
            'message'    => ['required', 'string'],
        ]);

        $thread = SupportThread::create([
            'user_id'    => $request->user()->id,
            'issue_type' => $data['issue_type'],
            'priority'   => $data['priority'] ?? 'medium',
            'status'     => 'open',
            'tx_id'      => $data['tx_id'] ?? null,
        ]);

        $senderType = $this->normalizeSenderType($request->user()->role);

        SupportMessage::create([
            'support_thread_id' => $thread->id,
            'sender_type'       => $senderType,
            'sender_id'         => $request->user()->id,
            'message'           => $data['message'],
        ]);

        $adminId = User::where('role', 'admin')->value('id');
        if ($adminId) {
            RetailerController::notify(
                $adminId,
                'support_ticket_created',
                'New support ticket',
                $data['issue_type'],
                ['ticket_id' => $thread->id]
            );
        }

        $thread->load('lastMessage');

        return response()->json($thread, 201);
    }

    public function show(Request $request, SupportThread $thread)
    {
        $this->authorizeThread($request, $thread);

        $thread->load(['messages' => function ($q) {
            $q->orderBy('created_at');
        }, 'user', 'admin']);

        return response()->json($thread);
    }

    public function reply(Request $request, SupportThread $thread)
    {
        $this->authorizeThread($request, $thread);

        $data = $request->validate([
            'message' => ['required', 'string'],
            'status'  => ['nullable', Rule::in(['open', 'in_progress', 'escalated', 'resolved'])],
        ]);

        $senderType = $this->normalizeSenderType($request->user()->role);

        SupportMessage::create([
            'support_thread_id' => $thread->id,
            'sender_type'       => $senderType,
            'sender_id'         => $request->user()->id,
            'message'           => $data['message'],
        ]);

        $thread->updated_at = now();
        if ($senderType === 'admin' && !$thread->admin_id) {
            $thread->admin_id = $request->user()->id;
        }
        if (!empty($data['status'])) {
            $thread->status = $data['status'];
        } elseif ($senderType === 'admin' && $thread->status === 'open') {
            $thread->status = 'in_progress';
        }
        $thread->save();

        if ($senderType !== 'admin') {
            $adminId = $thread->admin_id ?: User::where('role', 'admin')->value('id');
            if ($adminId) {
                RetailerController::notify(
                    $adminId,
                    'support_ticket_update',
                    'New support reply',
                    $thread->issue_type ?: 'Support ticket updated',
                    ['ticket_id' => $thread->id, 'status' => $thread->status]
                );
            }
        }

        return response()->json(['message' => 'Reply sent']);
    }

    protected function authorizeThread(Request $request, SupportThread $thread): void
    {
        $user = $request->user();
        if ($user->role === 'admin') {
            return;
        }
        if ($thread->user_id !== $user->id) {
            abort(403, 'Not allowed');
        }
    }

    private function normalizeSenderType(string $role): string
    {
        if ($role === 'user') {
            return 'retailer';
        }
        return $role;
    }
}
