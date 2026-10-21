<?php

namespace App\Modules\WhatsApp\Services;

/**
 * Whole-message STOP / START keywords for marketing opt-out.
 *
 * A match is the entire inbound text after trimming and stripping punctuation,
 * so a sentence that merely contains "stop" is left for the inbox and bots.
 */
class OptOutKeywordService
{
    public function classify(?string $text): ?string
    {
        $normalized = $this->normalize($text);
        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, $this->keywords('stop'), true)) {
            return 'stop';
        }

        if (in_array($normalized, $this->keywords('start'), true)) {
            return 'start';
        }

        return null;
    }

    public function reply(string $action): string
    {
        $key = $action === 'stop' ? 'stop_reply' : 'start_reply';
        $fallback = $action === 'stop'
            ? 'You are unsubscribed and will not receive marketing messages. Reply START to opt back in.'
            : 'You are subscribed again and can receive messages from us.';

        $configured = trim((string) config('whatsapp.opt_out.'.$key, ''));

        return $configured !== '' ? $configured : $fallback;
    }

    /**
     * @return list<string>
     */
    protected function keywords(string $which): array
    {
        $configured = config('whatsapp.opt_out.'.$which.'_keywords', []);
        if (is_string($configured)) {
            $configured = explode(',', $configured);
        }
        if (! is_array($configured)) {
            $configured = [];
        }

        $normalized = [];
        foreach ($configured as $keyword) {
            $keyword = $this->normalize((string) $keyword);
            if ($keyword !== '') {
                $normalized[] = $keyword;
            }
        }

        return array_values(array_unique($normalized));
    }

    protected function normalize(?string $text): string
    {
        $text = mb_strtolower(trim((string) $text));
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', '', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }
}
