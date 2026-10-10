<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract\Commerce;

/**
 * An active partner a code resolved to. `displayName` is safe to show the
 * buyer; `code` is the canonical spelling to store on the sale.
 */
final class ReferralMatch
{
    public function __construct(
        public readonly string $code,
        public readonly string $displayName,
    ) {
    }
}
