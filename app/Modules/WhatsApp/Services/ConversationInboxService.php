<?php

namespace App\Modules\WhatsApp\Services;

use App\Models\AccountModule;
use App\Models\AiAgent;
use App\Models\WhatsAppCall;
use App\Modules\Chatbots\Models\BotFlow;
use App\Modules\Chatbots\Models\BotNode;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use Illuminate\Support\Facades\Schema;

class ConversationInboxService
{
    public function activeAiAgentsForInbox($account): array
    {
        if (! $account || ! Schema::hasTable('ai_agents')) {
            return [];
        }

        return AiAgent::where('account_id', $account->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'avatar', 'role', 'tone', 'mode'])
            ->map(fn (AiAgent $agent) => [
                'id' => $agent->id,
                'name' => $agent->name,
                'avatar' => $agent->avatar,
                'role' => $agent->role,
                'tone' => $agent->tone,
                'mode' => $agent->mode,
            ])
            ->values()
            ->all();
    }

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

    public function callingSettingsForInbox($account): array
    {
        $defaults = [
            'enabled' => false,
            'routing_mode' => 'ai_first',
            'default_agent_id' => null,
            'outbound_enabled' => true,
            'outbound_requires_consent' => false,
            'transfer_number' => null,
            'setup_url' => null,
        ];

        // WhatsApp Calling is a ChatDesk paid plans / Zyptos feature.
        return $defaults;

        if (! $account || ! Schema::hasTable('account_modules')) {
            return $defaults;
        }

        $module = AccountModule::where('account_id', $account->id)
            ->whereIn('module_key', ['whatsapp.calling', 'ai.voice'])
            ->first();

        return array_merge($defaults, $module?->config ?? []);
    }

    public function recentCallsForInbox($account, array $waIds): array
    {
        if (! $account || ! Schema::hasTable('ai_voice_calls')) {
            return [];
        }

        $normalizedWaIds = collect($waIds)
            ->map(fn ($value) => preg_replace('/\D+/', '', (string) $value))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedWaIds->isEmpty()) {
            return [];
        }

        return WhatsAppCall::with('agent:id,name')
            ->where('account_id', $account->id)
            ->latest()
            ->limit(100)
            ->get()
            ->filter(fn (WhatsAppCall $call) => $normalizedWaIds->contains(preg_replace('/\D+/', '', (string) $call->phone_number)))
            ->take(50)
            ->map(fn (WhatsAppCall $call) => [
                'id' => $call->id,
                'whatsapp_connection_id' => $call->whatsapp_connection_id,
                'direction' => $call->direction,
                'phone_number' => $call->phone_number,
                'contact_name' => $call->contact_name,
                'status' => $call->status,
                'route_mode' => $call->route_mode,
                'routed_to' => $call->routed_to,
                'provider_call_id' => $call->provider_call_id,
                'duration_seconds' => $call->duration_seconds,
                'summary' => $call->summary,
                'metadata' => $call->metadata ?? [],
                'agent' => $call->agent ? [
                    'id' => $call->agent->id,
                    'name' => $call->agent->name,
                ] : null,
                'created_at' => $call->created_at?->toIso8601String(),
                'updated_at' => $call->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();
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
