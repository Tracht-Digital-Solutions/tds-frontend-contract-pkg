<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

/**
 * Who a presented site key belongs to — the result of {@see SiteKeys::verify()}.
 *
 * Deliberately a value object rather than a bare `true`: a caller that only
 * needs "is this key valid" ignores the fields, while `POST /tools/registry`
 * has to know it was the *tools* site that presented the key and not the blog.
 * Returning a boolean would have made that check impossible without a second
 * lookup, and the obvious workaround — trusting a `site` field the caller sent
 * alongside the key — is exactly the bug this shape prevents.
 *
 * Never carries the key itself. The plaintext exists in one place for one
 * moment (the response of `POST /admin/sites`); everything afterwards works
 * against a SHA-256 hash, so there is nothing here to leak into a log.
 */
final class SiteKeyIdentity
{
    public function __construct(
        /** Row id in `app_site_key` — the handle `DELETE /admin/sites/{id}` revokes. */
        public readonly int $id,
        /** Site id, e.g. `landingpage` / `blog` / `tools` / `auth`, or a custom one. */
        public readonly string $site,
        /** Human label shown in the panel; may be empty. */
        public readonly string $label = '',
        /** The origin declared when the key was issued; may be empty. */
        public readonly string $origin = '',
        /** CMS resource type (`blog`, `website`, `tools`) when paired. */
        public readonly ?string $resourceType = null,
        /** Stable id in that CMS resource type. */
        public readonly ?string $resourceId = null,
        /** Deterministic content bindings carried by this key. @var array<string,mixed> */
        public readonly array $bindings = [],
        /** Public route prefixes this key may read. @var list<string> */
        public readonly array $scopes = [],
    ) {
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
}
