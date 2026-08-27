<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

/** Refreshes the cache target belonging to one connected CMS resource. */
interface ConnectedSiteCache
{
    public function refresh(string $resourceType, string $resourceId, CacheEvent $event): CacheResult;
}
