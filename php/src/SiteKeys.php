<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

/**
 * Verify a **site key** — the credential that binds one of the public static
 * sites (landingpage, blog, tools, auth, or a custom one) to this API.
 *
 * Bound into the base's container under this interface, exactly like
 * {@see Mailer}, {@see SettingsStore} and {@see UserContext}, so a module can
 * resolve it null-safely (`$c->has(SiteKeys::class)`) and keep working when the
 * base predates the feature or has no database yet.
 *
 * ### Why the sites need a credential at all
 *
 * They are static bundles on their own hosts; their content comes from this API
 * at BUILD time. Until now that read was anonymous, so nothing anywhere could
 * answer "is the blog actually talking to us, and when did it last do so" — and
 * because every build-time fetch is fail-soft (`return []` on any failure), a
 * site whose API had moved silently rendered its baked fallbacks instead. The
 * key is what makes the connection a fact the panel can show.
 *
 * ### Enforcement is a policy, not a property of the key
 *
 * A valid key is always honoured. Whether a MISSING key is tolerated is
 * {@see enforcement()}, and it is deliberately three-valued rather than a
 * boolean — `warn` is the migration path that lets an operator see which sites
 * are still keyless *before* anything starts being rejected. The same shape
 * the support-tickets `ingest_mode` uses, for the same reason: switching
 * straight from "everything allowed" to "everything checked" breaks whatever
 * you forgot, in production, with no prior signal.
 *
 * ### Implementations must not throw
 *
 * This sits in front of the public read surface. Without a database — the state
 * the frontend service is in until `services/frontend/.env` exists — it must
 * degrade to `off`, never to a 500 on every content route.
 */
interface SiteKeys
{
    /**
     * Check a plaintext key and record the use.
     *
     * Returns the owning identity, or `null` when the key is unknown, revoked,
     * or belongs to a different site than `$site` demands. A successful call
     * updates the key's "last used" bookkeeping — that timestamp is the panel's
     * only evidence that a site is really connected, so verification and
     * recording are one operation on purpose: a caller that had to remember a
     * second `touch()` would eventually forget it.
     *
     * @param string      $key    The plaintext presented by the caller.
     * @param string|null $site   Restrict to one site id; null accepts any.
     * @param string|null $origin The caller's `Origin`, recorded when present.
     */
    public function verify(string $key, ?string $site = null, ?string $origin = null): ?SiteKeyIdentity;

    /**
     * What happens to a protected route reached WITHOUT a valid key.
     *
     *   - `off`     — nothing. The default, and the behaviour of every
     *                 deployment that predates this feature.
     *   - `warn`    — served, but counted and logged, so the panel can report
     *                 "N keyless reads since …" while you roll keys out.
     *   - `enforce` — 401.
     *
     * @return 'off'|'warn'|'enforce'
     */
    public function enforcement(): string;
}
