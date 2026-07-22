<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

/**
 * The core's runtime settings store, exposed to modules through the Slim app's
 * DI container (`$app->getContainer()->get(SettingsStore::class)`). A namespaced
 * key/value store so third-party config (DeepL keys, rebuild tokens, …) is
 * panel-editable instead of `.env`-only.
 *
 * The BASE binds a concrete implementation (a table + AES-256-GCM for secrets);
 * extensions read their own namespace with the **DB-first, env-fallback** pattern:
 * a non-empty stored value wins, else the env var, else a coded default (so
 * existing `.env` deployments keep working and boot stays DB-free). Secrets are
 * never returned raw by the admin API — only masked (`configured` + `last4`) —
 * but {@see getSecret()} decrypts for the module that owns the namespace.
 *
 * Namespaces are per-extension (`blog-cms`, `website-cms`, …) so keys don't
 * collide in the shared store. When the core has no store bound (e.g. an
 * extension's isolated unit test), a module should fall back to env.
 */
interface SettingsStore
{
    /** Plaintext value for a non-secret key, or $default when absent/empty. */
    public function get(string $namespace, string $key, ?string $default = null): ?string;

    /** Decrypted secret for a secret key, or null when absent/undecryptable. */
    public function getSecret(string $namespace, string $key): ?string;

    /** Upsert a value; a secret is encrypted at rest. */
    public function set(string $namespace, string $key, string $value, bool $secret): void;

    public function delete(string $namespace, string $key): void;

    /**
     * Masked view of a namespace for the admin API — a secret is returned only as
     * `configured` + `last4`, never raw; a non-secret returns its value.
     *
     * @return list<array<string,mixed>>
     */
    public function allMasked(string $namespace): array;
}
