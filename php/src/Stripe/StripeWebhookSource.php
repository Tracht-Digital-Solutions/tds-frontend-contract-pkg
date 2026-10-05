<?php

declare(strict_types=1);

namespace Tds\Frontend\Contract\Stripe;

/**
 * A module that receives Stripe webhooks says so, so the admin panel's
 * Zahlungen section can list every endpoint to register in the Stripe
 * dashboard — URL, events, and whether its signing secret is stored. Before,
 * the URLs existed only in each module's README.
 */
interface StripeWebhookSource
{
    /** @return list<StripeWebhookDef> */
    public function stripeWebhooks(): array;
}
