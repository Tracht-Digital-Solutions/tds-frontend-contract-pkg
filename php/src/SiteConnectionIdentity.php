<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

/** Request-bound identity used for deterministic public content selection. */
final class SiteConnectionIdentity
{
    /** @param array<string,mixed> $bindings @param list<string> $scopes */
    public function __construct(
        public readonly ?int $siteKeyId = null,
        public readonly string $site = '',
        public readonly ?string $resourceType = null,
        public readonly ?string $resourceId = null,
        public readonly array $bindings = [],
        public readonly array $scopes = [],
    ) {
    }

    public function isConnected(): bool
    {
        return $this->siteKeyId !== null && $this->resourceType !== null && $this->resourceId !== null;
    }

    public function allows(string $route): bool
    {
        if ($this->scopes === []) {
            return true;
        }
        foreach ($this->scopes as $prefix) {
            $prefix = rtrim($prefix, '/');
            if ($route === $prefix || str_starts_with($route, $prefix . '/')) {
                return true;
            }
        }
        return false;
    }

    public function binding(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->bindings) ? $this->bindings[$key] : $default;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'site_key_id' => $this->siteKeyId,
            'site' => $this->site,
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
            'bindings' => (object) $this->bindings,
            'scopes' => $this->scopes,
        ];
    }
}
