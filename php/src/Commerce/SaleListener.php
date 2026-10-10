<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract\Commerce;

/**
 * Optional {@see \Tds\Frontend\Contract\Module} capability: react to sales other
 * modules complete (a referral commission, a report, a follow-up).
 *
 * The seller never knows its listeners. It calls {@see SaleEvents}, which the
 * base builds from every module implementing this interface.
 *
 * ### Rules
 *
 * - **Idempotent.** `onSalePaid()` arrives again for every webhook redelivery;
 *   key your rows by `(source, sourceId)`.
 * - **Never throw.** The call sits inside a payment webhook, and a provider
 *   retries everything that is not a 2xx. {@see SaleEvents} guards each call
 *   anyway, but a swallowed exception is a silent loss — handle your own.
 * - **Cheap.** No network calls; the webhook is waiting.
 */
interface SaleListener
{
    public function onSalePaid(SaleEvent $sale): void;

    /** The sale was refunded or voided. May arrive for sales never seen as paid. */
    public function onSaleReversed(string $source, string $sourceId): void;
}
