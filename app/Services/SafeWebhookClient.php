<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\IpUtils;

class SafeWebhookClient
{
    public function send(string $url, string $method, array $payload, int $timeout = 10): Response
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'] ?? '', '[]'));
        if (! in_array($scheme, ['http', 'https'], true) || $host === ''
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Webhook URL must be a public HTTP or HTTPS address without credentials or fragments.');
        }

        $method = strtoupper($method);
        if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true)) {
            throw new InvalidArgumentException('Unsupported webhook method.');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);
        if ($addresses === []) {
            throw new InvalidArgumentException('Webhook hostname could not be resolved.');
        }
        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                || IpUtils::checkIp($address, [
                    ...IpUtils::PRIVATE_SUBNETS,
                    '100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '198.18.0.0/15',
                    '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4',
                    '2001:db8::/32', '2001:2::/48', '2001:10::/28', '2001:20::/28', '3fff::/20', 'ff00::/8',
                ])
                || (str_contains($address, ':') && ! IpUtils::checkIp($address, '2000::/3'))) {
                throw new InvalidArgumentException('Webhook destination must resolve only to public IP addresses.');
            }
        }

        if (! extension_loaded('curl')) {
            throw new \RuntimeException('The cURL extension is required for secure webhook delivery.');
        }
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $address = str_contains($addresses[0], ':') ? '['.$addresses[0].']' : $addresses[0];

        // Pin the checked DNS result for the actual connection. Never follow a redirect
        // or use an environment proxy that could resolve the hostname a second time.
        return Http::connectTimeout(5)->timeout(max(1, min(30, $timeout)))
            ->withOptions([
                'allow_redirects' => false,
                'proxy' => '',
                'curl' => filter_var($host, FILTER_VALIDATE_IP) ? [] : [CURLOPT_RESOLVE => ["{$host}:{$port}:{$address}"]],
            ])
            ->send($method, $url, in_array($method, ['GET', 'HEAD'], true) ? ['query' => $payload] : ['json' => $payload]);
    }

    protected function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        return array_values(array_unique(array_filter(array_map(
            static fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
            $records ?: []
        ))));
    }
}
