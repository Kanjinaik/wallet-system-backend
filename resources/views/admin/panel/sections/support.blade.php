<div class="panel">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
        <div>
            <h3 style="margin:0;">Retailer Chat</h3>
            <p class="tiny" style="margin:4px 0 0 0;">View retailer tickets and reply from here.</p>
        </div>
    </div>

    <div class="row2" style="grid-template-columns: 320px 1fr; gap: 18px; margin-top:16px;">
        <div class="panel" style="padding:16px; height: 70vh; overflow-y: auto;">
            <h4 style="margin-top:0;">Conversations</h4>
            @forelse($supportThreads as $thread)
                <a class="menu support-thread {{ ($activeSupportThread?->id === $thread->id)?'active':'' }}"
                   href="{{ route('admin.support', ['thread_id' => $thread->id]) }}"
                   style="display:block; margin-bottom:8px; padding:10px 12px; border-radius:10px;">
                    <div class="menu-left">
                        <span class="menu-icon">&#128172;</span>
                        <div style="display:flex; flex-direction:column;">
                            <strong class="support-thread-title">{{ $thread->issue_type }}</strong>
                            <span class="tiny">{{ $thread->user?->name ?? 'Retailer' }}</span>
                            <span class="tiny">Status: {{ ucfirst(str_replace('_',' ', $thread->status)) }}</span>
                        </div>
                    </div>
                    <div class="tiny" style="margin-left:32px;">Txn: {{ $thread->tx_id ?: 'N/A' }}</div>
                </a>
            @empty
                <div class="tiny">No tickets yet.</div>
            @endforelse
        </div>

        <div class="panel" style="padding:16px; height: 70vh; display:flex; flex-direction:column;">
            @if($activeSupportThread)
                <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
                    <div>
                        <p class="tiny" style="margin:0;">Ticket #{{ $activeSupportThread->id }}</p>
                        <h4 style="margin:4px 0 0 0;">{{ $activeSupportThread->issue_type }}</h4>
                        <p class="tiny" style="margin:4px 0;">{{ $activeSupportThread->user?->name ?? 'Retailer' }} &middot; Status: {{ ucfirst(str_replace('_',' ', $activeSupportThread->status)) }}</p>
                    </div>
                    <form method="POST" action="{{ route('admin.support.reply', $activeSupportThread->id) }}">
                        @csrf
                        <input type="hidden" name="status" value="resolved">
                        <button class="btn gray" type="submit">Mark Resolved</button>
                    </form>
                </div>

                <div style="flex:1; overflow-y:auto; margin:14px 0; padding-right:6px;">
                    @foreach($activeSupportThread->messages as $msg)
                        <div class="support-message-row {{ $msg->sender_type === 'admin' ? 'support-message-row-admin' : 'support-message-row-user' }}">
                            <div class="support-message-bubble {{ $msg->sender_type === 'admin' ? 'support-message-bubble-admin' : 'support-message-bubble-user' }}">
                                <div class="support-message-sender">{{ ucfirst($msg->sender_type) }}</div>
                                <div class="support-message-text">{{ $msg->message }}</div>
                                <div class="tiny support-message-time">{{ optional($msg->created_at)->timezone('Asia/Kolkata')->format('d M, h:i a') }}</div>
                            </div>
                        </div>
                    @endforeach
                    @if(!$activeSupportThread->messages->count())
                        <div class="tiny">No messages yet.</div>
                    @endif
                </div>

                <form method="POST" action="{{ route('admin.support.reply', $activeSupportThread->id) }}" style="display:flex; gap:10px; align-items:flex-start;">
                    @csrf
                    <textarea class="support-reply-input" name="message" rows="3" required placeholder="Type a reply..."></textarea>
                    <select name="status" class="support-select">
                        <option value="">Status...</option>
                        <option value="open" {{ $activeSupportThread->status==='open'?'selected':'' }}>Open</option>
                        <option value="in_progress" {{ $activeSupportThread->status==='in_progress'?'selected':'' }}>In Progress</option>
                        <option value="escalated" {{ $activeSupportThread->status==='escalated'?'selected':'' }}>Escalated</option>
                        <option value="resolved" {{ $activeSupportThread->status==='resolved'?'selected':'' }}>Resolved</option>
                    </select>
                    <button class="btn" type="submit">Send</button>
                </form>
            @else
                <p class="tiny">No ticket selected.</p>
            @endif
        </div>
    </div>
</div>

<style>
    .support-thread {
        color: var(--muted);
    }
    .support-thread-title {
        color: var(--heading);
    }
    .support-thread.active .support-thread-title {
        color: #1f6ee0;
    }
    .support-message-row {
        margin-bottom: 12px;
        display: flex;
    }
    .support-message-row-admin {
        justify-content: flex-end;
    }
    .support-message-row-user {
        justify-content: flex-start;
    }
    .support-message-bubble {
        display: inline-block;
        max-width: 80%;
        padding: 12px 14px;
        border-radius: 14px;
        border: 1px solid #dbe6f3;
        box-shadow: 0 8px 20px rgba(143, 168, 199, 0.12);
    }
    .support-message-bubble-user {
        background: linear-gradient(180deg, #ffffff 0%, #f6faff 100%);
    }
    .support-message-bubble-admin {
        background: linear-gradient(180deg, #edf5ff 0%, #dfeeff 100%);
        border-color: #c8dcf7;
    }
    .support-message-sender {
        font-weight: 700;
        color: var(--heading);
        margin-bottom: 4px;
    }
    .support-message-text {
        color: var(--text);
        line-height: 1.55;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .support-message-time {
        margin-top: 6px;
        color: #6f7f97;
    }
    .support-reply-input {
        flex: 1;
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid #d9e5f2;
        background: #ffffff;
        color: var(--text);
        outline: none;
        resize: vertical;
        min-height: 96px;
    }
    .support-reply-input::placeholder {
        color: #7f8ca3;
    }
    .support-reply-input:focus {
        border-color: #92c4ff;
        box-shadow: 0 0 0 4px rgba(56, 150, 255, 0.12);
    }
    .support-select {
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid #d9e5f2;
        background: #ffffff;
        color: var(--text);
        min-width: 160px;
    }
    .support-select:focus {
        outline: none;
        border-color: #92c4ff;
        box-shadow: 0 0 0 4px rgba(56, 150, 255, 0.12);
    }
    .support-select option {
        background: #ffffff;
        color: var(--text);
    }
</style>
