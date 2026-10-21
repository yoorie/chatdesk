<?php

namespace App\Services\AI;

use App\Core\Billing\EntitlementService;
use App\Core\Billing\UsageService;
use App\Models\AiAgent;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Support\Collection;

class ConversationAssistantService
{
    /**
     * Generate a suggested reply for the agent based on recent conversation messages.
     */
    public function suggestReply(
        WhatsAppConversation $conversation,
        int $lastMessagesLimit = 25,
        ?string $customInstruction = null,
        ?AiAgent $agent = null,
        bool $directSend = false,
        ?int $maxTokensOverride = null
    ): string {
        $conversation->loadMissing(['contact', 'account']);

        $provider = AiProviderFactory::forAccount($conversation->account);
        $settings = AiProviderFactory::generationSettingsForAccount($conversation->account);
        $systemPrompt = $settings['system_prompt'] ?: $this->defaultSystemPrompt();
        $systemPrompt = $this->applyAgentInstruction($systemPrompt, $agent, $directSend);
        $systemPrompt = $this->applyCustomInstruction($systemPrompt, $customInstruction);
        $systemPrompt = $this->applyPaymentUrlGuardrail($systemPrompt);
        $temperature = (float) ($settings['temperature'] ?? 0.3);
        $maxTokens = $maxTokensOverride
            ? max(120, min(3000, $maxTokensOverride))
            : max(500, min(3000, (int) ($settings['max_tokens'] ?? 1200)));

        $contactName = $conversation->contact?->name ?? $conversation->contact?->wa_id ?? 'Customer';

        $messages = WhatsAppMessage::where('whatsapp_conversation_id', $conversation->id)
            ->orderBy('created_at', 'desc')
            ->limit($lastMessagesLimit)
            ->get(['direction', 'text_body', 'type', 'created_at'])
            ->reverse()
            ->values();

        $conversationText = $this->formatMessagesForPrompt($messages);
        $userPrompt = $this->buildUserPrompt($contactName, $conversationText);

        $platformBacked = AiProviderFactory::willUsePlatformProvider($conversation->account);
        if ($platformBacked && $conversation->account) {
            app(EntitlementService::class)->assertWithinLimit($conversation->account, 'ai_credits_monthly', 1);
        }

        try {
            $reply = $provider->generate($systemPrompt, $userPrompt, $temperature, $maxTokens);
        } catch (\Throwable $e) {
            if (! AiProviderFactory::hasWorkspaceProvider($conversation->account)
                || ! AiProviderFactory::shouldFallbackToPlatform($conversation->account)) {
                throw $e;
            }

            $platformBacked = true;
            $reply = AiProviderFactory::fromPlatformSettings()
                ->generate($systemPrompt, $userPrompt, $temperature, $maxTokens);
        }

        $reply = AiReplyGuardrail::sanitize($reply);
        $estimatedTokens = $this->estimateTokens($systemPrompt."\n".$userPrompt."\n".$reply);
        app(UsageService::class)->recordAiRequest(
            $conversation->account,
            $estimatedTokens,
            0,
            $platformBacked
        );

        if ($agent) {
            $agent->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $reply;
    }

    protected function applyAgentInstruction(string $systemPrompt, ?AiAgent $agent, bool $directSend = false): string
    {
        if (! $agent) {
            return $systemPrompt;
        }

        $lines = [
            "AI agent: {$agent->name}",
            "Agent role: {$agent->role}",
            "Tone: {$agent->tone}",
            "Language preference: {$agent->language}",
            "Operating mode: {$agent->mode}",
        ];

        if ($agent->goal) {
            $lines[] = "Goal:\n".trim($agent->goal);
        }

        if ($agent->instructions) {
            $lines[] = "Agent instructions:\n".trim($agent->instructions);
        }

        $knowledgeSources = array_filter($agent->knowledge_sources ?? []);
        if ($knowledgeSources) {
            $lines[] = 'Allowed workspace knowledge sources: '.implode(', ', $knowledgeSources).'.';
        }

        $allowedActions = array_filter($agent->allowed_actions ?? []);
        if ($allowedActions) {
            $lines[] = 'Allowed actions: '.implode(', ', $allowedActions).'. Do not perform or promise actions outside this list.';
        }

        $qualificationFields = array_filter($agent->qualification_fields ?? []);
        if ($qualificationFields) {
            $lines[] = 'Qualify the customer by collecting these fields over the conversation when useful: '.implode(', ', $qualificationFields).'.';
        }

        $guardrails = array_filter($agent->guardrails ?? []);
        if ($guardrails) {
            $lines[] = 'Guardrails: '.implode(', ', $guardrails).'.';
        }

        $keywords = array_filter($agent->escalation_rules['keywords'] ?? []);
        if ($keywords) {
            $lines[] = 'Escalate or ask a human to review when the conversation contains: '.implode(', ', $keywords).'.';
        }

        $handoffKeywords = array_filter($agent->handoff_rules['keywords'] ?? []);
        if ($handoffKeywords) {
            $lines[] = 'Handoff rules: stop trying to resolve and ask for a human when these appear: '.implode(', ', $handoffKeywords).'.';
        }

        if ($agent->fallback_reply) {
            $lines[] = 'When unsure, use this fallback style/message: '.trim($agent->fallback_reply);
        }

        if ($agent->max_reply_chars) {
            $lines[] = 'Maximum reply length: '.$agent->max_reply_chars.' characters.';
        }

        if ($agent->mode !== 'autopilot' && ! $directSend) {
            $lines[] = 'Draft only. A human will review before sending.';
        }

        return $systemPrompt."\n\nWorkspace AI agent profile:\n".implode("\n", $lines);
    }

    protected function applyCustomInstruction(string $systemPrompt, ?string $customInstruction): string
    {
        $customInstruction = trim((string) $customInstruction);
        if ($customInstruction === '') {
            return $systemPrompt;
        }

        return $systemPrompt."\n\nAdditional agent instruction:\n".$customInstruction;
    }

    protected function applyPaymentUrlGuardrail(string $systemPrompt): string
    {
        return $systemPrompt."\n\nNon-negotiable commerce guardrail:\n".AiReplyGuardrail::paymentUrlInstruction();
    }

    protected function formatMessagesForPrompt(Collection $messages): string
    {
        $lines = [];
        foreach ($messages as $msg) {
            $label = $msg->direction === 'inbound' ? 'Customer' : 'Agent';
            $body = $msg->text_body ?? $this->summarizeNonTextMessage($msg->type);
            $body = trim((string) $body);
            if ($body !== '') {
                $lines[] = "{$label}: {$body}";
            }
        }

        return implode("\n", $lines);
    }

    protected function summarizeNonTextMessage(string $type): string
    {
        return match ($type) {
            'image' => '[Image]',
            'audio', 'video' => '[Media]',
            'document' => '[Document]',
            'location' => '[Location]',
            'interactive', 'button' => '[Interactive message]',
            'template' => '[Template message]',
            default => '[Message]',
        };
    }

    protected function buildUserPrompt(string $contactName, string $conversationText): string
    {
        if ($conversationText === '') {
            return "WhatsApp chat with {$contactName}. No messages yet. Suggest a friendly opening message to start the conversation. Keep it natural and complete.";
        }

        return "WhatsApp chat with {$contactName}.\n\nRecent messages:\n{$conversationText}\n\nWrite the next customer-facing reply as the agent. Use WhatsApp formatting only: *bold*, _italic_, ~strikethrough~, and simple hyphen bullets. Do not use Markdown **bold**, headings, tables, or fenced code blocks. Use 2-6 short lines when needed. If the customer asks about pricing, plans, billing, features, or a demo, answer with the available specific details and a clear next step. Never invent checkout or payment URLs; use the public pricing page for pricing unless an actual generated payment link is provided in the conversation. Keep it conversational and on-topic. Do not include greetings like 'Hi' at the start if the conversation is already ongoing. Do not end mid-sentence.";
    }

    protected function estimateTokens(string $text): int
    {
        return max(1, (int) ceil(strlen($text) / 4));
    }

    protected function defaultSystemPrompt(): string
    {
        return 'You are a helpful assistant writing reply text for support and sales agents in WhatsApp conversations. Output only the customer-facing reply text, nothing else. Use WhatsApp formatting only: *bold*, _italic_, ~strikethrough~, and simple hyphen bullets. Never use Markdown **bold**, headings, tables, or fenced code blocks. Be concise but complete, professional, and friendly. Do not add quotes or labels. Never cut off the final sentence.';
    }
}
