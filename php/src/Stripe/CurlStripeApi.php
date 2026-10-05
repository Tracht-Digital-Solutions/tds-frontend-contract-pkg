<?php

declare(strict_types=1);

namespace Tds\Frontend\Contract\Stripe;

/**
 * StripeApi over plain ext-curl (no SDK, the extension convention).
 *
 * Merged from the three module clients: the shop's bracket flattening (so
 * nested `line_items` and `metadata` encode correctly and booleans become
 * "true"/"false"), billing's 20s timeout and error wording, and an optional
 * idempotency key none of them sent.
 */
final class CurlStripeApi implements StripeApi
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $baseUrl = 'https://api.stripe.com/v1',
    ) {
    }

    public function isConfigured(): bool
    {
        return trim($this->secretKey) !== '';
    }

    public function mode(): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }
        // sk_test_… / rk_test_… vs sk_live_… / rk_live_…
        return str_contains(substr($this->secretKey, 0, 8), '_test') ? 'test' : 'live';
    }

    public function post(string $path, array $params = [], ?string $idempotencyKey = null): array
    {
        $headers = ['Content-Type: application/x-www-form-urlencoded'];
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }
        return $this->send('POST', $path, http_build_query(self::flatten($params), '', '&'), $headers);
    }

    public function get(string $path, array $query = []): array
    {
        $qs = $query === [] ? '' : '?' . http_build_query(self::flatten($query), '', '&');
        return $this->send('GET', $path . $qs, null, []);
    }

    /**
     * @param list<string> $headers
     * @return array<string,mixed>
     */
    private function send(string $method, string $path, ?string $body, array $headers): array
    {
        if (!$this->isConfigured()) {
            throw new StripeException('Stripe ist nicht konfiguriert.', 0);
        }
        $ch = curl_init($this->baseUrl . $path);
        if ($ch === false) {
            throw new StripeException('Stripe-Anfrage konnte nicht initialisiert werden.', 0);
        }
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array_merge(['Authorization: Bearer ' . $this->secretKey], $headers),
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 5,
        ];
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $body ?? '';
        }
        curl_setopt_array($ch, $options);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new StripeException('Stripe nicht erreichbar: ' . $error, 0);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $decoded = json_decode((string) $raw, true);
        $decoded = is_array($decoded) ? $decoded : [];
        if ($status < 200 || $status >= 300) {
            $message = isset($decoded['error']['message']) && is_string($decoded['error']['message'])
                ? $decoded['error']['message']
                : 'HTTP ' . $status;
            throw new StripeException('Stripe: ' . $message, $status);
        }
        return $decoded;
    }

    /**
     * Nested arrays → Stripe's bracket notation; null dropped; bools as words.
     *
     * @param  array<string|int,mixed> $data
     * @return array<string,scalar>
     */
    public static function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            $name = $prefix === '' ? (string) $key : "{$prefix}[{$key}]";
            if (is_array($value)) {
                $out += self::flatten($value, $name);
                continue;
            }
            $out[$name] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
        }
        return $out;
    }
}
