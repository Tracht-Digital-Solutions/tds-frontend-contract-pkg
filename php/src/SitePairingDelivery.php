<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

/** Secret-free outcome of delivering a pairing invitation to a public site. */
final class SitePairingDelivery
{
    public function __construct(
        public readonly bool $delivered,
        public readonly string $status,
        public readonly ?SiteConnection $connection,
        public readonly ?string $fallbackUrl,
        public readonly string $expiresAt,
        public readonly ?string $error = null,
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'delivered' => $this->delivered,
            'status' => $this->status,
            'connection' => $this->connection?->toArray(),
            'fallback_url' => $this->fallbackUrl,
            'expires_at' => $this->expiresAt,
            'error' => $this->error,
        ];
    }
}
