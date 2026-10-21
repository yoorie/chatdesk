<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\Chatbots\Models\BotFlow;
use App\Modules\Chatbots\Models\BotNode;
use App\Modules\WhatsApp\Models\WhatsAppConversation;

class ConversationInboxService
{
    public function automationStateForConversation(WhatsAppConversation $conversation): ?array
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $session = $metadata['automation_session'] ?? null;
        if (! is_array($session) || empty($session['flow_id']) || empty($session['waiting_node_id'])) {
            return null;
        }

        $flow = BotFlow::query()
            ->where('account_id', $conversation->account_id)
            ->where('id', (int) $session['flow_id'])
            ->first();
        $node = BotNode::query()
            ->where('account_id', $conversation->account_id)
            ->where('id', (int) $session['waiting_node_id'])
            ->first();

        $nodeConfig = is_array($node?->config) ? $node->config : [];

        return [
            'bot_id' => isset($session['bot_id']) ? (int) $session['bot_id'] : null,
            'flow_id' => (int) $session['flow_id'],
            'flow_name' => $flow?->name ?? 'Automation flow',
            'waiting_node_id' => (int) $session['waiting_node_id'],
            'waiting_node_label' => $nodeConfig['label']
                ?? $nodeConfig['title']
                ?? $nodeConfig['header_text']
                ?? $nodeConfig['body_text']
                ?? 'Waiting for reply',
            'status' => 'waiting',
            'bot_paused' => false,
            'invalid_replies' => (int) ($session['invalid_replies'] ?? 0),
            'updated_at' => $session['updated_at'] ?? null,
        ];
    }

    public function isAutomationProcessing(WhatsAppConversation $conversation): bool
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        if (! ($metadata['automation_processing'] ?? false)) {
            return false;
        }

        $expiresAt = $metadata['automation_processing_expires_at'] ?? null;
        if (! $expiresAt) {
            return true;
        }

        try {
            if (now()->lt(\Illuminate\Support\Carbon::parse($expiresAt))) {
                return true;
            }
        } catch (\Throwable) {
            return true;
        }

        unset(
            $metadata['automation_processing'],
            $metadata['automation_processing_mode'],
            $metadata['automation_processing_started_at'],
            $metadata['automation_processing_expires_at'],
            $metadata['automation_processing_message_id']
        );
        $conversation->forceFill(['metadata' => $metadata])->save();

        return false;
    }

    public function ctwaForConversation(WhatsAppConversation $conversation): ?array
    {
        $conversationMeta = is_array($conversation->metadata) ? $conversation->metadata : [];
        $contactMeta = is_array($conversation->contact?->metadata) ? $conversation->contact->metadata : [];
        $ctwa = $conversationMeta['ctwa']['latest']
            ?? $conversationMeta['ctwa']
            ?? $contactMeta['ctwa']['latest']
            ?? $contactMeta['ctwa']
            ?? null;

        if (! is_array($ctwa) || $ctwa === []) {
            return null;
        }

        return [
            'source' => $ctwa['source'] ?? 'click_to_whatsapp_ad',
            'source_type' => $ctwa['source_type'] ?? null,
            'source_id' => $ctwa['source_id'] ?? null,
            'source_url' => $ctwa['source_url'] ?? null,
            'ctwa_clid' => $ctwa['ctwa_clid'] ?? null,
            'headline' => $ctwa['headline'] ?? null,
            'body' => $ctwa['body'] ?? null,
            'media_type' => $ctwa['media_type'] ?? null,
            'captured_at' => $ctwa['captured_at'] ?? null,
        ];
    }
}
