<?php

namespace App\Modules\WhatsApp\Events\Inbox;

use App\Modules\WhatsApp\Models\WhatsAppConversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuditEventAdded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WhatsAppConversation $conversation,
        public array $auditEvent
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $accountId = $this->conversation->account_id;
        $conversationId = $this->conversation->id;

        return [
            new PrivateChannel("account.{$accountId}.whatsapp.conversation.{$conversationId}")];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'whatsapp.audit.added';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'audit_event' => $this->auditEvent];
    }
}
