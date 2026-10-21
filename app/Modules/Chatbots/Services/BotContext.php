<?php

namespace App\Modules\Chatbots\Services;

use App\Models\Account;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;

/**
 * Context object passed to trigger/condition/action evaluators.
 */
class BotContext
{
    public function __construct(
        public Account $account,
        public WhatsAppConversation $conversation,
        public WhatsAppMessage $inboundMessage,
        public WhatsAppConnection $connection,
        public array $metadata = []
    ) {}

    public function getMessageText(): ?string
    {
        if ($this->inboundMessage->text_body) {
            return $this->inboundMessage->text_body;
        }

        $payload = $this->inboundMessage->payload ?? [];
        $interactive = is_array($payload) ? ($payload['interactive'] ?? null) : null;
        if (is_array($interactive)) {
            return $interactive['button_reply']['title']
                ?? $interactive['button_reply']['id']
                ?? $interactive['list_reply']['title']
                ?? $interactive['list_reply']['id']
                ?? null;
        }

        return null;
    }

    public function getConversationStatus(): string
    {
        return $this->conversation->status ?? 'open';
    }

    public function getConnectionId(): int
    {
        return $this->connection->id;
    }

    public function getContactSource(): ?string
    {
        return $this->conversation->contact?->source;
    }

    public function getCtwaReferral(): ?array
    {
        $payload = is_array($this->inboundMessage->payload) ? $this->inboundMessage->payload : [];
        if (is_array($payload['ctwa'] ?? null)) {
            return $payload['ctwa'];
        }

        $conversationMeta = is_array($this->conversation->metadata) ? $this->conversation->metadata : [];
        $contactMeta = is_array($this->conversation->contact?->metadata) ? $this->conversation->contact->metadata : [];
        $ctwa = $conversationMeta['ctwa']['latest']
            ?? $conversationMeta['ctwa']
            ?? $contactMeta['ctwa']['latest']
            ?? $contactMeta['ctwa']
            ?? null;

        return is_array($ctwa) ? $ctwa : null;
    }

    public function isFirstMessage(): bool
    {
        // Check if this is the first message in the conversation
        return $this->conversation->messages()
            ->where('direction', 'inbound')
            ->count() === 1;
    }
}
