<?php

namespace App\Support;

/**
 * Keeps raw Meta/Graph errors, exception text and internal jargon out of
 * customer-facing flash messages. Plain sentences pass through unchanged.
 *
 * Mirrors resources/js/lib/friendly.ts so server flashes and client toasts
 * read the same.
 */
class CustomerMessage
{
    /** @var array<string, string> */
    private const META_CODES = [
        '4' => 'Too many requests right now. Please wait a minute and try again.',
        '10' => 'Your WhatsApp account has not given this app permission for this action. Reconnect your WhatsApp account.',
        '100' => 'Some details were not accepted by WhatsApp. Please check them and try again.',
        '190' => 'Your WhatsApp connection has expired. Reconnect your WhatsApp account to continue.',
        '200' => 'Your WhatsApp account has not given this app permission for this action. Reconnect your WhatsApp account.',
        '368' => 'WhatsApp has temporarily limited this number for policy reasons.',
        '80007' => 'Too many requests right now. Please wait a minute and try again.',
        '130429' => 'You are sending messages too quickly. Please try again shortly.',
        '131000' => 'Something went wrong on WhatsApp. Please try again.',
        '131005' => 'Your WhatsApp account does not have permission for this action.',
        '131008' => 'A required field is missing. Please check the message and try again.',
        '131009' => 'One of the values in this message is not valid. Please check it and try again.',
        '131016' => 'WhatsApp is temporarily unavailable. Please try again in a few minutes.',
        '131021' => 'You cannot send a message to your own business number.',
        '131026' => 'This message could not be delivered. The number may not use WhatsApp or may need to update the app.',
        '131031' => 'Your WhatsApp account has been locked by Meta. Check Meta Business Suite for details.',
        '131037' => 'Your WhatsApp display name needs approval before you can send messages.',
        '131042' => 'There is a payment problem on your Meta account. Add or update a payment method in Meta Business Suite.',
        '131045' => 'Your business phone number needs to be registered again. Reconnect your WhatsApp account.',
        '131047' => 'More than 24 hours have passed since the customer last replied. Send an approved template to restart the chat.',
        '131048' => 'Too many of your messages were reported as spam, so sending is limited for now.',
        '131049' => 'WhatsApp held back this marketing message to keep a healthy experience for the customer. Try again later.',
        '131050' => 'This customer has stopped receiving marketing messages from you.',
        '131051' => 'This message type is not supported.',
        '131052' => 'The media file could not be downloaded. Please ask the customer to send it again.',
        '131053' => 'The media file could not be uploaded. Check the file type and size and try again.',
        '131056' => 'Too many messages were sent to this customer in a short time. Please wait and try again.',
        '132000' => 'The template variables do not match the approved template. Check the values and try again.',
        '132001' => 'This template is not available in the selected language, or is not approved yet.',
        '132005' => 'The message is too long once the variables are filled in. Shorten the values and try again.',
        '132007' => 'This template breaks a WhatsApp content rule. Edit it and submit it again.',
        '132012' => 'The template variables are in the wrong format. Check the values and try again.',
        '132015' => 'This template has been paused by WhatsApp because of low quality. Edit it or use another template.',
        '132016' => 'This template has been disabled by WhatsApp. Use another template.',
        '133010' => 'This phone number is not registered with WhatsApp yet. Reconnect your WhatsApp account.',
        '2388024' => 'A template with this name already exists. Pick a different name.',
        '2388093' => 'This template name is already in use in this language. Pick a different name.',
    ];

    /** Words customers should never see, mapped to plain replacements. */
    private const JARGON = [
        '/\bWABA accounts?\b/i' => 'WhatsApp Business account',
        '/\bWABA ID\b/i' => 'WhatsApp Business account ID',
        '/\bWABA\b/' => 'WhatsApp Business account',
        '/\bphone number ID\b/i' => 'business number',
        '/\bMeta webhook unsubscribe failed\b/i' => 'we could not stop WhatsApp updates for it',
        '/\bManual renewal\b/i' => 'Renewal',
        '/\bRazorpay one-time\b/i' => 'Online payment',
    ];

    public static function humanize(mixed $message, string $fallback = 'Something went wrong. Please try again.'): mixed
    {
        if (! is_string($message)) {
            return $message;
        }

        $message = trim($message);
        if ($message === '') {
            return $message;
        }

        // A technical tail after a plain prefix: "Template sync failed: (#100) ...".
        $prefix = null;
        if (preg_match('/^([A-Z][^:]{2,80}?):\s+(.+)$/s', $message, $parts) && self::looksTechnical($parts[2])) {
            $prefix = rtrim($parts[1], '.');
            $message = $parts[2];
        }

        $code = self::metaCode($message);
        if ($code !== null) {
            return self::withPrefix($prefix, self::META_CODES[$code]);
        }

        $patterns = [
            '/access token|session has expired|error validating|oauthexception|token.*(expired|invalid)/i' => self::META_CODES['190'],
            '/24.?hour|re-?engagement|outside.*(customer service|session) window/i' => self::META_CODES['131047'],
            '/rate limit|too many (calls|requests)/i' => self::META_CODES['4'],
            '/undeliverable|not a valid whatsapp/i' => self::META_CODES['131026'],
            '/curl error|could not resolve host|connection (timed out|refused)|operation timed out/i' => 'We could not reach WhatsApp just now. Please try again in a moment.',
            '/sqlstate|stack trace|undefined (index|offset|array key)|call to (a )?member function/i' => 'Something went wrong on our side. Please try again, or contact support if it keeps happening.',
        ];
        foreach ($patterns as $pattern => $friendly) {
            if (preg_match($pattern, $message)) {
                return self::withPrefix($prefix, $friendly);
            }
        }

        if (self::looksTechnical($message)) {
            return self::withPrefix($prefix, $fallback);
        }

        if ($prefix !== null) {
            $message = $prefix.': '.$message;
        }

        return preg_replace(array_keys(self::JARGON), array_values(self::JARGON), $message) ?? $message;
    }

    private static function withPrefix(?string $prefix, string $friendly): string
    {
        if ($prefix === null) {
            return $friendly;
        }

        $prefix = preg_replace(array_keys(self::JARGON), array_values(self::JARGON), $prefix) ?? $prefix;
        // Prefixes that are themselves technical ("Webhook subscription failed") add nothing.
        if (preg_match('/webhook|provider|payload|graph|api/i', $prefix)) {
            return $friendly;
        }

        return $prefix.'. '.$friendly;
    }

    private static function metaCode(string $message): ?string
    {
        foreach (['/\(#(\d{1,8})\)/', '/"code"\s*:\s*(\d{1,8})/', '/\bcode[\s:=]+(\d{1,8})\b/i', '/\berror(?:\s+code)?\s*[:#]?\s*(\d{3,8})\b/i'] as $pattern) {
            if (preg_match($pattern, $message, $match) && isset(self::META_CODES[$match[1]])) {
                return $match[1];
            }
        }

        return null;
    }

    private static function looksTechnical(string $message): bool
    {
        return (bool) (
            preg_match('/\(#\d+\)/', $message)
            || preg_match('/\b(OAuthException|GraphMethodException|Graph API|graph\.facebook|fbtrace_id|error_subcode|error_data)\b/i', $message)
            || preg_match('/^\s*[\{\[]/', $message)
            || preg_match('/\b(phone_number_id|waba_id|business_account_id|access_token)\b|wamid\./i', $message)
            || preg_match('/\b(SQLSTATE|Stack trace)\b|[A-Za-z]+Exception\b/', $message)
            || preg_match('/HTTP request returned status code \d+/i', $message)
        );
    }
}
