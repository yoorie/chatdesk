<?php

namespace Tests\Feature;

use App\Services\SafeWebhookClient;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SafeWebhookClientTest extends TestCase
{
    public static function blockedDestinations(): array
    {
        return array_map(fn ($url) => [$url], [
            'http://127.0.0.1', 'http://10.0.0.1', 'http://169.254.169.254',
            'http://100.100.100.200', 'http://[::1]', 'http://[::ffff:127.0.0.1]',
            'file:///etc/passwd', 'https://user:password@example.com',
            'http://224.0.0.1', 'http://192.0.2.1', 'http://[2001:db8::1]',
            'http://[ff02::1]', 'http://[64:ff9b::7f00:1]',
        ]);
    }

    #[DataProvider('blockedDestinations')]
    public function test_private_and_unsupported_destinations_are_never_requested(string $url): void
    {
        Http::fake();
        try {
            app(SafeWebhookClient::class)->send($url, 'POST', []);
            $this->fail('Unsafe destination was accepted.');
        } catch (InvalidArgumentException) {
            Http::assertNothingSent();
        }
    }

    public function test_mixed_public_private_dns_results_are_rejected(): void
    {
        Http::fake();
        $client = new class extends SafeWebhookClient
        {
            protected function resolve(string $host): array
            {
                return ['93.184.216.34', '192.168.1.1'];
            }
        };
        $this->expectException(InvalidArgumentException::class);
        $client->send('https://hooks.example.com', 'POST', []);
    }

    public function test_public_destination_is_pinned_and_redirects_are_disabled(): void
    {
        Http::fake(function ($request, array $options) {
            $this->assertFalse($options['allow_redirects']);
            $this->assertSame('', $options['proxy']);
            $this->assertSame(['hooks.example.com:443:93.184.216.34'], $options['curl'][CURLOPT_RESOLVE]);

            return Http::response('', 302, ['Location' => 'http://127.0.0.1']);
        });
        $client = new class extends SafeWebhookClient
        {
            protected function resolve(string $host): array
            {
                return ['93.184.216.34'];
            }
        };
        $response = $client->send('https://hooks.example.com/event', 'POST', ['test' => true]);
        $this->assertSame(302, $response->status());
        Http::assertSentCount(1);
        $options = Http::recorded()[0][0]->toPsrRequest();
        $this->assertSame('hooks.example.com', $options->getUri()->getHost());
    }
}
