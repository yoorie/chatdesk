<?php

namespace App\Modules\WhatsApp\Events\Inbox;

use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WhatsAppMessage $message
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $accountId = $this->message->account_id;
        $conversationId = $this->message->whatsapp_conversation_id;

        return [
            new PrivateChannel("account.{$accountId}.whatsapp.conversation.{$conversationId}")];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'whatsapp.message.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'account_id' => $this->message->account_id,
            'conversation_id' => $this->message->whatsapp_conversation_id,
            'message' => [
                'id' => $this->message->id,
                'direction' => $this->message->direction,
                'type' => $this->message->type,
                'text_body' => $this->message->text_body,
                'payload' => $this->message->payload,
                'status' => $this->message->status,
                'error_message' => $this->message->error_message,
                'meta_message_id' => $this->message->meta_message_id,
                'created_at' => $this->message->created_at->toIso8601String(),
                'updated_at' => $this->message->updated_at?->toIso8601String(),
                'sent_at' => $this->message->sent_at?->toIso8601String(),
                'delivered_at' => $this->message->delivered_at?->toIso8601String(),
                'read_at' => $this->message->read_at?->toIso8601String()]];
    }
}
