<?php

namespace App\Modules\Chatbots\Services;

use App\Modules\Chatbots\Models\BotFlow;

class TriggerEvaluator
{
    /**
     * Check if a flow's trigger matches the context.
     */
    public function matches(BotFlow $flow, BotContext $context): bool
    {
        $trigger = $flow->trigger;
        $type = $trigger['type'] ?? null;

        return match ($type) {
            'inbound_message' => $this->matchesInboundMessage($trigger, $context),
            'keyword' => $this->matchesKeyword($trigger, $context),
            'button_reply' => $this->matchesButtonReply($trigger, $context),
            'form_submission' => $this->matchesFormSubmission($trigger, $context),
            default => false,
        };
    }

    protected function matchesFormSubmission(array $trigger, BotContext $context): bool
    {
        $payload = is_array($context->inboundMessage->payload) ? $context->inboundMessage->payload : [];
        if (($payload['source'] ?? null) !== 'public_form') {
            return false;
        }

        $surveyId = isset($trigger['survey_id']) ? (int) $trigger['survey_id'] : 0;
        if ($surveyId > 0 && (int) ($payload['survey_id'] ?? 0) !== $surveyId) {
            return false;
        }

        return true;
    }

    protected function matchesInboundMessage(array $trigger, BotContext $context): bool
    {
        // Check if only first message
        if ($trigger['first_message_only'] ?? false) {
            if (! $context->isFirstMessage()) {
                return false;
            }
        }

        // Check connection filter
        if (isset($trigger['connection_ids']) && is_array($trigger['connection_ids'])) {
            $allowedConnectionIds = array_values(array_unique(array_map(
                static fn ($id) => (int) $id,
                array_filter($trigger['connection_ids'], static fn ($id) => is_numeric($id))
            )));

            if (! in_array((int) $context->getConnectionId(), $allowedConnectionIds, true)) {
                return false;
            }
        }

        // Check if conversation already assigned (optional skip)
        if ($trigger['skip_if_assigned'] ?? false) {
            if ($context->conversation->assigned_to) {
                return false;
            }
        }

        if (! $this->matchesSourceFilters($trigger, $context)) {
            return false;
        }

        return true;
    }

    protected function matchesSourceFilters(array $trigger, BotContext $context): bool
    {
        $requiredSource = $trigger['source'] ?? $trigger['contact_source'] ?? null;
        if (is_string($requiredSource) && trim($requiredSource) !== '' && trim($requiredSource) !== 'any') {
            if ($requiredSource === 'ctwa') {
                if (! $context->getCtwaReferral()) {
                    return false;
                }
            } elseif ($context->getContactSource() !== $requiredSource) {
                return false;
            }
        }

        $allowedCtwaSourceIds = $trigger['ctwa_source_ids'] ?? [];
        if (is_array($allowedCtwaSourceIds) && $allowedCtwaSourceIds !== []) {
            $ctwa = $context->getCtwaReferral();
            if (! $ctwa) {
                return false;
            }

            $sourceId = (string) ($ctwa['source_id'] ?? '');
            $allowed = collect($allowedCtwaSourceIds)
                ->filter(fn ($id) => is_scalar($id) && trim((string) $id) !== '')
                ->map(fn ($id) => trim((string) $id))
                ->values()
                ->all();

            if ($allowed !== [] && ! in_array($sourceId, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    protected function matchesKeyword(array $trigger, BotContext $context): bool
    {
        $keywords = $trigger['keywords'] ?? [];
        if (! is_array($keywords)) {
            return false;
        }
        $keywords = array_values(array_filter(array_map(
            static fn ($keyword) => is_string($keyword) ? trim($keyword) : '',
            $keywords
        ), static fn ($keyword) => $keyword !== ''));
        if (empty($keywords)) {
            return false;
        }

        $text = $context->getMessageText() ?? '';
        $caseSensitive = $trigger['case_sensitive'] ?? false;
        $wholeWord = $trigger['whole_word'] ?? false;
        $matchType = $trigger['match_type'] ?? 'any'; // any|all

        if (! $caseSensitive) {
            $text = mb_strtolower($text);
        }

        $matches = [];
        foreach ($keywords as $keyword) {
            $searchKeyword = $caseSensitive ? $keyword : mb_strtolower($keyword);

            if ($wholeWord) {
                $pattern = '/\b'.preg_quote($searchKeyword, '/').'\b/';
                $matches[] = preg_match($pattern, $text) === 1;
            } elseif ($this->shouldForceWordBoundary($searchKeyword)) {
                $pattern = '/(?<![\p{L}\p{N}_])'.preg_quote($searchKeyword, '/').'(?![\p{L}\p{N}_])/u';
                $matches[] = preg_match($pattern, $text) === 1;
            } else {
                $matches[] = str_contains($text, $searchKeyword);
            }
        }

        return $matchType === 'all'
            ? count(array_filter($matches)) === count($keywords)
            : count(array_filter($matches)) > 0;
    }

    protected function shouldForceWordBoundary(string $keyword): bool
    {
        return preg_match('/^[\p{L}\p{N}_]{1,3}$/u', $keyword) === 1;
    }

    protected function matchesButtonReply(array $trigger, BotContext $context): bool
    {
        // Check if message has interactive payload
        $payload = $context->inboundMessage->payload ?? [];
        $interactive = $payload['interactive'] ?? null;

        if (! $interactive) {
            return false;
        }

        $buttonId = $interactive['button_reply']['id'] ?? $interactive['list_reply']['id'] ?? null;
        $expectedButtonId = $trigger['button_id'] ?? null;

        return $buttonId === $expectedButtonId;
    }
}
