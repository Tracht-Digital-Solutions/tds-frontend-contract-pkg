<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

/** Public, secret-free view of one CMS resource's public-site connection. */
final class SiteConnection
{
    public const NEEDS_PAIRING = 'needs_pairing';
    public const PENDING = 'pending';
    public const CONNECTED = 'connected';

    /** @param array<string,mixed> $bindings @param list<string> $scopes */
    public function __construct(
        public readonly int $id,
        public readonly string $resourceType,
        public readonly string $resourceId,
        public readonly string $origin,
        public readonly string $profile,
        public readonly array $bindings = [],
        public readonly array $scopes = [],
        public readonly string $status = self::NEEDS_PAIRING,
        public readonly ?int $siteKeyId = null,
        public readonly ?string $pairedAt = null,
        public readonly ?string $lastSeenAt = null,
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
            'origin' => $this->origin,
            'profile' => $this->profile,
            'bindings' => (object) $this->bindings,
            'scopes' => $this->scopes,
            'status' => $this->status,
            'site_key_id' => $this->siteKeyId,
            'paired_at' => $this->pairedAt,
            'last_seen_at' => $this->lastSeenAt,
        ];
    }
}
