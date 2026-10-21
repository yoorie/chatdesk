<?php

namespace Tests\Unit;

use App\Support\CustomerMessage;
use PHPUnit\Framework\TestCase;

class CustomerMessageTest extends TestCase
{
    public function test_plain_sentences_pass_through(): void
    {
        $this->assertSame('Template saved.', CustomerMessage::humanize('Template saved.'));
    }

    public function test_meta_error_codes_become_human_sentences(): void
    {
        $message = CustomerMessage::humanize('(#131047) Re-engagement message');

        $this->assertStringContainsString('24 hours', $message);
        $this->assertStringNotContainsString('#131047', $message);
    }

    public function test_technical_tail_keeps_the_plain_prefix(): void
    {
        $message = CustomerMessage::humanize('Template sync failed: (#100) Invalid parameter');

        $this->assertStringStartsWith('Template sync failed.', $message);
        $this->assertStringNotContainsString('(#100)', $message);
    }

    public function test_expired_tokens_ask_to_reconnect(): void
    {
        $message = CustomerMessage::humanize('Error validating access token: Session has expired');

        $this->assertStringContainsString('Reconnect your WhatsApp account', $message);
    }

    public function test_billing_and_meta_jargon_is_replaced(): void
    {
        $this->assertStringNotContainsString('WABA', CustomerMessage::humanize('Your WABA is not connected.'));
        $this->assertStringNotContainsString('Manual renewal', CustomerMessage::humanize('Manual renewal invoice created.'));
        $this->assertStringNotContainsString('Razorpay one-time', CustomerMessage::humanize('Razorpay one-time payment started.'));
    }

    public function test_non_strings_are_returned_unchanged(): void
    {
        $this->assertNull(CustomerMessage::humanize(null));
        $this->assertSame(['a'], CustomerMessage::humanize(['a']));
    }
}
