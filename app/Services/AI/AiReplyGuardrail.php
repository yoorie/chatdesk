<?php

namespace App\Services\AI;

class AiReplyGuardrail
{
    public static function paymentUrlInstruction(): string
    {
        return implode(' ', [
            'Payment URL rule:',
            ('Never invent or guess '.brand_name().' checkout, payment, Razorpay, invoice, or subscription URLs.'),
            'Do not use /checkout URLs.',
            ('For '.brand_name().' plan purchase questions, share the public pricing page ('.url('/pricing').') or ask the customer to open Billing in their '.brand_name().' dashboard.'),
            'Only send a direct payment link when an actual generated payment URL is already provided by the system, recent messages, or tool output.',
        ]);
    }

    public static function sanitize(string $reply): string
    {
        $reply = trim($reply);

        if ($reply === '') {
            return '';
        }

        $reply = preg_replace(
            '~https?://(?:www\.)?'.preg_quote((string) parse_url(url('/'), PHP_URL_HOST), '~').'/checkout/[^\s)>\]]+~i',
            url('/pricing'),
            $reply
        ) ?? $reply;

        $reply = preg_replace(
            ('~\b(?:Here is|Here\'s)\s+your\s+(?:'.brand_name().'\s+)?payment\s+link\b~i'),
            ('Here is the '.brand_name().' pricing page'),
            $reply
        ) ?? $reply;

        return trim($reply);
    }

    public static function isInvalidPlatformPaymentUrl(string $url): bool
    {
        return preg_match('~^https?://(?:www\.)?'.preg_quote((string) parse_url(url('/'), PHP_URL_HOST), '~').'/checkout(?:/|$)~i', trim($url)) === 1;
    }
}
