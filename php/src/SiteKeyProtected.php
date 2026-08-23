<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

/**
 * Optional {@see Module} capability: declare which of this module's routes are
 * **public site reads** — the ones a static frontend fetches at build time, and
 * therefore the ones a site key may be required for.
 *
 * ### Why the module declares them and not the base
 *
 * The base owns the middleware but must not own the paths. `/content/blog`
 * belongs to blog-cms, `/content/landing` to website-cms and `/tools/catalog`
 * to tools; a coded list in the base would rot the moment an extension renamed
 * a route, and the rot would be *silent* — a prefix that no longer matches
 * simply stops being protected. This is the same ownership rule that put route
 * grouping behind `ModuleRegistry::routeOwners()` and route prose behind
 * {@see ApiDocSource}.
 *
 * ### Prefixes, not patterns
 *
 * The middleware runs before routing resolves a pattern, so it compares the
 * request path against these as **prefixes** (`/content/blog` also covers
 * `/content/blog/mein-artikel`). Keep them specific: `/content` would be a
 * correct prefix for blog-cms and would also silently swallow website-cms's
 * routes, which is how one module ends up gating another's surface.
 *
 * ### What must NOT be listed
 *
 * Only routes a *site* reads. Never an admin route (those are already gated by
 * `UserContext::isAdmin()`, and a site key must never be an alternative path to
 * one), and never a route the browser calls on a live page — the contact form,
 * the live-chat widget, the account menu. Those run in a visitor's browser,
 * which has no key and never will; listing one turns enforcement into an outage
 * on the public site.
 *
 * Opt-in and must not throw, like the other optional capabilities: a module
 * with nothing to protect simply does not implement the interface.
 */
interface SiteKeyProtected
{
    /**
     * Path prefixes of this module's public site-read routes.
     *
     * @return list<string> e.g. `['/content/blog', '/content/topics']`
     */
    public function siteKeyRoutes(): array;
}
