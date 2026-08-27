<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

/** One short-lived, single-use pairing invitation. */
final class SitePairing
{
    /** @param array<string,mixed> $bindings @param list<string> $scopes */
    public function __construct(
        public readonly string $pairingId,
        public readonly string $pairingToken,
        public readonly string $resourceType,
        public readonly string $resourceId,
        public readonly string $origin,
        public readonly string $profile,
        public readonly array $bindings,
        public readonly array $scopes,
        public readonly string $expiresAt,
    ) {
    }

    /** Fragment transport keeps the token out of access logs and referrers. */
    public function installUrl(?string $apiBase = null): string
    {
        $fragment = http_build_query(array_filter([
            'pairing_token' => $this->pairingToken,
            'api_base' => $apiBase,
            'profile' => $this->profile,
        ], static fn ($value): bool => $value !== null && $value !== ''));

        return rtrim($this->origin, '/') . '/install#' . $fragment;
    }

    /** @return array<string,mixed> */
    public function toArray(?string $apiBase = null): array
    {
        return [
            'pairing_id' => $this->pairingId,
            'pairing_token' => $this->pairingToken,
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
            'origin' => $this->origin,
            'profile' => $this->profile,
            'bindings' => (object) $this->bindings,
            'scopes' => $this->scopes,
            'expires_at' => $this->expiresAt,
            'install_url' => $this->installUrl($apiBase),
        ];
    }
}
