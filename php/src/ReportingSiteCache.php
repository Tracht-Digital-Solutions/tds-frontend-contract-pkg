<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

/** Additive, reporting counterpart to the legacy fire-and-forget SiteCache. */
interface ReportingSiteCache extends SiteCache
{
    /** @param CacheEvent[] $events */
    public function rebuildWithResult(string $baseUrl, ?string $token, array $events): CacheResult;
}
