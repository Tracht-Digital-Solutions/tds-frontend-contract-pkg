<?php

declare(strict_types=1);

namespace Tds\Frontend\Contract\Stripe;

/**
 * Stripe webhook signature check (the `Stripe-Signature` header scheme):
 * HMAC-SHA256 over "{t}.{payload}" with the endpoint's signing secret, any
 * `v1` may match, and a timestamp outside the tolerance is a replay.
 *
 * Pure and static, so it is unit-testable without Stripe. Three modules had a
 * verbatim copy ("ported verbatim (third time)").
 */
final class StripeWebhook
{
    /**
     * @param string   $payload   raw request body (unmodified bytes)
     * @param string   $sigHeader the `Stripe-Signature` header value
     * @param string   $secret    the endpoint signing secret (whsec_…)
     * @param int      $tolerance max |now − t| in seconds (0 = skip the time check)
     * @param int|null $now       injectable clock for tests
     */
    public static function verify(
        string $payload,
        string $sigHeader,
        string $secret,
        int $tolerance = 300,
        ?int $now = null,
    ): bool {
        if ($secret === '' || $sigHeader === '') {
            return false;
        }
        [$timestamp, $signatures] = self::parseHeader($sigHeader);
        if ($timestamp === null || $signatures === []) {
            return false;
        }
        if ($tolerance > 0 && abs(($now ?? time()) - $timestamp) > $tolerance) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }
        return false;
    }

    /** @return array{0: int|null, 1: list<string>} */
    private static function parseHeader(string $header): array
    {
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) {
                continue;
            }
            [$key, $value] = $kv;
            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }
        return [$timestamp, $signatures];
    }
}
