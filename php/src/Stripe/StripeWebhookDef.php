<?php

declare(strict_types=1);

namespace Tds\Frontend\Contract\Stripe;

/** One webhook endpoint a module expects Stripe to call. */
final class StripeWebhookDef
{
    /**
     * @param string       $label           German, shown in the admin list ("Rechnungen").
     * @param string       $path            Route path, e.g. "/billing/webhook".
     * @param list<string> $events          Event types to subscribe in Stripe.
     * @param string       $secretNamespace Settings namespace holding the signing secret.
     * @param string       $secretKey       Settings key of the signing secret (secret setting).
     */
    public function __construct(
        public readonly string $label,
        public readonly string $path,
        public readonly array $events,
        public readonly string $secretNamespace,
        public readonly string $secretKey,
    ) {
    }
}
