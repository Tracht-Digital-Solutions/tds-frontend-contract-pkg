<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

/**
 * Tells a public site to rebuild the cached HTML of the pages one content
 * change affects, exposed to modules through the DI container
 * (`$app->getContainer()->get(SiteCache::class)`).
 *
 * The public sites render server-side (Astro SSR) behind a file-backed
 * full-page cache: a cached path is served straight off disk by the web server
 * and never wakes the Node process, which is what keeps a dynamic site as fast
 * as the static build it replaced. The consequence is that a saved block or
 * post is invisible until its page is rendered again — this interface is how a
 * module says so.
 *
 * **The BASE binds the implementation**, exactly like {@see Mailer}: extensions
 * hold no HTTP client, no token and no URL policy of their own. That is also
 * why this is an interface rather than a service class copied per extension —
 * `RebuildTrigger` already exists three times, near byte-identical, in
 * website-cms, blog-cms and tools, and every future fix has to be made three
 * times.
 *
 * **It never throws and never fails a save.** A site that is down, moved or
 * simply not configured yet must not turn "save this article" into an error;
 * the article is saved, the public page stays a little stale, and the operator
 * has a "Cache neu bauen" button to catch up. Implementations log and swallow.
 *
 * Not to be confused with the workflow-dispatch rebuild the CMS modules still
 * carry: that one rebuilds the *repository* and is for design and code changes.
 * This one only re-renders pages whose CONTENT moved.
 */
interface SiteCache
{
    /**
     * Ask the site at `$baseUrl` to re-render the pages `$events` affect.
     *
     * @param string       $baseUrl Origin of the public site, e.g.
     *                              `https://blog.tracht-digital.de`. An empty
     *                              string means "not configured" and is a no-op.
     * @param string|null  $token   The site's cache token. Null/empty is a
     *                              no-op: an unauthenticated rebuild call would
     *                              be a free render-DoS on a public host.
     * @param CacheEvent[] $events  What changed. An empty list is a no-op.
     */
    public function rebuild(string $baseUrl, ?string $token, array $events): void;

    /**
     * Whether a call would actually go out (both URL and token present).
     *
     * Lets an admin surface say "Cache-URL nicht konfiguriert" in the flow
     * instead of reporting a cheerful success for a request nobody sent.
     */
    public function isConfigured(string $baseUrl, ?string $token): bool;
}
