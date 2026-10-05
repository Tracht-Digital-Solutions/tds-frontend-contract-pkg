<?php

declare(strict_types=1);

namespace Tds\Frontend\Contract\Tests;

use PHPUnit\Framework\TestCase;
use Tds\Frontend\Contract\Stripe\CurlStripeApi;
use Tds\Frontend\Contract\Stripe\StripeException;
use Tds\Frontend\Contract\Stripe\StripeWebhook;

final class StripeTest extends TestCase
{
    public function test_mode_comes_from_the_key(): void
    {
        self::assertSame('test', (new CurlStripeApi('sk_test_abc'))->mode());
        self::assertSame('live', (new CurlStripeApi('sk_live_abc'))->mode());
        self::assertSame('test', (new CurlStripeApi('rk_test_abc'))->mode());
        self::assertNull((new CurlStripeApi(''))->mode());
        self::assertFalse((new CurlStripeApi('  '))->isConfigured());
    }

    public function test_an_unconfigured_client_refuses_before_any_request(): void
    {
        $this->expectException(StripeException::class);
        (new CurlStripeApi(''))->post('/customers', ['name' => 'x']);
    }

    public function test_flatten_uses_bracket_notation(): void
    {
        self::assertSame(
            [
                'mode' => 'payment',
                'line_items[0][price_data][unit_amount]' => 4900,
                'metadata[order]' => 'TDS-1',
                'automatic_tax[enabled]' => 'true',
            ],
            CurlStripeApi::flatten([
                'mode' => 'payment',
                'line_items' => [['price_data' => ['unit_amount' => 4900]]],
                'metadata' => ['order' => 'TDS-1'],
                'automatic_tax' => ['enabled' => true],
                'skipped' => null,
            ]),
        );
    }

    public function test_webhook_signature(): void
    {
        $payload = '{"type":"invoice.paid"}';
        $t = 1_700_000_000;
        $sig = hash_hmac('sha256', $t . '.' . $payload, 'whsec_x');

        self::assertTrue(StripeWebhook::verify($payload, "t={$t},v1={$sig}", 'whsec_x', 300, $t + 10));
        self::assertFalse(StripeWebhook::verify($payload, "t={$t},v1={$sig}", 'whsec_y', 300, $t + 10));
        self::assertFalse(StripeWebhook::verify($payload, "t={$t},v1={$sig}", 'whsec_x', 300, $t + 400), 'replay window');
        self::assertFalse(StripeWebhook::verify($payload, '', 'whsec_x'));
    }
}
