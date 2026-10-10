<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract\Commerce;

/** One line of a {@see SaleEvent}: which product, and its NET line total. */
final class SaleLine
{
    public function __construct(
        public readonly ?string $productId,
        public readonly int $netCents,
        public readonly string $title = '',
    ) {
    }
}
