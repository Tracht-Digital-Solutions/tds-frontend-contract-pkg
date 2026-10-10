<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract\Commerce;

/**
 * Optional {@see \Tds\Frontend\Contract\Module} capability: turn a partner code
 * (from a `?ref=` link or typed at checkout) into the partner it belongs to.
 *
 * A seller asks through {@see SaleEvents::resolveReferral()} so it can show
 * "Empfohlen von …" and drop codes nobody owns. An unknown code is never an
 * error: it must not block a purchase. Never throw, no network calls.
 */
interface ReferralResolver
{
    public function resolveReferral(string $code): ?ReferralMatch;
}
