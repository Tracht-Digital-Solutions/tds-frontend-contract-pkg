<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

/** Core-owned connection lifecycle used by CMS extensions. */
interface SiteConnections
{
    public function get(string $resourceType, string $resourceId): ?SiteConnection;

    /** @param array<string,mixed> $bindings @param list<string> $scopes */
    public function createPairing(
        string $resourceType,
        string $resourceId,
        string $origin,
        string $profile,
        array $bindings = [],
        array $scopes = [],
    ): SitePairing;

    /** Deliver server-to-server. The secret is returned only inside a fallback URL fragment. */
    public function deliverPairing(SitePairing $pairing, string $apiBase): SitePairingDelivery;

    public function delete(string $resourceType, string $resourceId): bool;
}
