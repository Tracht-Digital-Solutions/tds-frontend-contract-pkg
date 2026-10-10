<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract\Commerce;

/**
 * A sale that has been paid — a shop order or an invoice — as one module tells
 * the others about it (see {@see SaleListener}, {@see SaleEvents}).
 *
 * `(source, sourceId)` identifies the sale across redeliveries: a payment
 * provider repeats a webhook until it gets a 2xx, and the selling module
 * dispatches on EVERY paid delivery (the first one may have been the one where
 * a listener failed). Listeners therefore key their own rows by this pair.
 *
 * Amounts are NET cents. Commission, reporting and anything else derived from a
 * sale works on the net value; VAT belongs to the invoice.
 */
final class SaleEvent
{
    /**
     * @param string               $source        selling module, e.g. `shop`, `billing`
     * @param string               $sourceId      the sale's id inside that module
     * @param list<SaleLine>       $lines         optional breakdown; empty when the seller has none
     * @param string|null          $referralCode  partner code the buyer arrived with, verbatim
     * @param string|null          $referralVia   `link` (from `?ref=`) or `code` (typed at checkout)
     * @param string|null          $referredByNote free text "who recommended us?", never a code
     */
    public function __construct(
        public readonly string $source,
        public readonly string $sourceId,
        public readonly int $netCents,
        public readonly ?string $customerEmail = null,
        public readonly array $lines = [],
        public readonly ?string $referralCode = null,
        public readonly ?string $referralVia = null,
        public readonly ?string $referredByNote = null,
        public readonly ?\DateTimeImmutable $paidAt = null,
    ) {
    }
}
