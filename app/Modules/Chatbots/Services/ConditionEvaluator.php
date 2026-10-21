<?php

namespace App\Modules\Chatbots\Services;

use App\Modules\Chatbots\Models\BotNode;

class ConditionEvaluator
{
    /**
     * Evaluate a condition node against context.
     */
    public function evaluate(BotNode $node, BotContext $context): bool
    {
        $config = $node->config;
        $type = $config['type'] ?? null;

        return match ($type) {
            'text_contains' => $this->textContains($config, $context),
            'text_equals' => $this->textEquals($config, $context),
            'text_starts_with' => $this->textStartsWith($config, $context),
            'regex_match' => $this->regexMatch($config, $context),
            'time_window' => $this->timeWindow($config, $context),
            'connection_is' => $this->connectionIs($config, $context),
            'conversation_status' => $this->conversationStatus($config, $context),
            'tags_contains' => $this->tagsContains($config, $context),
            default => true, // Unknown condition passes
        };
    }

    protected function textContains(array $config, BotContext $context): bool
    {
        $text = $context->getMessageText() ?? '';
        $search = $config['value'] ?? '';
        $caseSensitive = $config['case_sensitive'] ?? false;

        if (! $caseSensitive) {
            $text = mb_strtolower($text);
            $search = mb_strtolower($search);
        }

        return str_contains($text, $search);
    }

    protected function textEquals(array $config, BotContext $context): bool
    {
        $text = $context->getMessageText() ?? '';
        $expected = $config['value'] ?? '';
        $caseSensitive = $config['case_sensitive'] ?? false;

        if (! $caseSensitive) {
            $text = mb_strtolower($text);
            $expected = mb_strtolower($expected);
        }

        return $text === $expected;
    }

    protected function textStartsWith(array $config, BotContext $context): bool
    {
        $text = $context->getMessageText() ?? '';
        $prefix = $config['value'] ?? '';
        $caseSensitive = $config['case_sensitive'] ?? false;

        if (! $caseSensitive) {
            $text = mb_strtolower($text);
            $prefix = mb_strtolower($prefix);
        }

        return str_starts_with($text, $prefix);
    }

    protected function regexMatch(array $config, BotContext $context): bool
    {
        $text = $context->getMessageText() ?? '';
        $pattern = $config['pattern'] ?? '';

        if (empty($pattern)) {
            return false;
        }

        try {
            return preg_match($pattern, $text) === 1;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function timeWindow(array $config, BotContext $context): bool
    {
        $timezone = $config['timezone'] ?? 'UTC';
        $startTime = $config['start_time'] ?? '00:00';
        $endTime = $config['end_time'] ?? '23:59';
        $days = $config['days'] ?? [0, 1, 2, 3, 4, 5, 6]; // 0 = Sunday
        if (! is_array($days)) {
            $days = [];
        }
        $days = array_values(array_unique(array_map(
            static fn ($day) => (int) $day,
            array_filter($days, static fn ($day) => is_numeric($day))
        )));

        $now = now()->setTimezone($timezone);
        $currentDay = (int) $now->format('w');
        $currentTime = $now->format('H:i');

        if (! in_array($currentDay, $days, true)) {
            return false;
        }

        return $currentTime >= $startTime && $currentTime <= $endTime;
    }

    protected function connectionIs(array $config, BotContext $context): bool
    {
        $connectionIds = $config['connection_ids'] ?? [];
        if (! is_array($connectionIds)) {
            $connectionIds = [$connectionIds];
        }
        $connectionIds = array_values(array_unique(array_map(
            static fn ($id) => (int) $id,
            array_filter($connectionIds, static fn ($id) => is_numeric($id))
        )));

        return in_array((int) $context->getConnectionId(), $connectionIds, true);
    }

    protected function conversationStatus(array $config, BotContext $context): bool
    {
        $expectedStatus = $config['status'] ?? 'open';

        return $context->getConversationStatus() === $expectedStatus;
    }

    protected function tagsContains(array $config, BotContext $context): bool
    {
        $contact = $context->conversation->contact;
        if (! $contact) {
            return false;
        }

        $tagIds = $config['tag_ids'] ?? [];
        $tagNames = $config['tags'] ?? $config['tag_names'] ?? [];

        if (! is_array($tagIds)) {
            $tagIds = [$tagIds];
        }
        if (! is_array($tagNames)) {
            $tagNames = [$tagNames];
        }

        if (! empty($tagIds)) {
            return $contact->tags()->whereIn('contact_tags.id', $tagIds)->exists();
        }

        if (! empty($tagNames)) {
            return $contact->tags()->whereIn('contact_tags.name', $tagNames)->exists();
        }

        return false;
    }
}
