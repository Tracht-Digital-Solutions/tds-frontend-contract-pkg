<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

/**
 * Optional {@see Module} capability: describe this module's HTTP routes for the
 * admin frontend's API reference (`GET /wiki.json` in the base API).
 *
 * ### Why the base cannot do this alone
 *
 * The base already knows every route: it introspects Slim's `RouteCollector`
 * after composition, so a new module route shows up in the reference with no
 * UI change. What introspection CANNOT recover is everything a reader actually
 * needs — what the route is for, which query parameters it honours, what it
 * answers with, and which permission it checks. That knowledge lives with the
 * module, so it is contributed here.
 *
 * ### Introspection stays authoritative
 *
 * The base LEFT-JOINs these docs onto the introspected route list, keyed by
 * `"<METHOD> <pattern>"`. A route nobody documented still appears (flagged
 * `documented: false`) rather than vanishing, and a doc entry whose route no
 * longer exists is reported instead of silently describing nothing. Documenting
 * is therefore always additive and can never hide part of the API.
 *
 * ### Like NotificationSource, this is opt-in and must not throw
 *
 * There is deliberately no manifest slot and no `AbstractModule` default: a
 * module that has nothing to say simply does not implement the interface. An
 * implementation that throws would take down the whole reference — including
 * the other twelve modules' routes — so build the array and return it; never
 * touch the database or the network from here.
 *
 * ### Keeping the docs honest
 *
 * A doc entry is prose next to code, which is the kind of thing that rots. Each
 * module ships a test asserting that its documented set and its registered set
 * are the same set, so renaming a path fails the module's own suite rather than
 * quietly degrading the reference.
 */
interface ApiDocSource
{
    /**
     * Descriptions of the routes this module mounts in {@see Module::register()}.
     *
     * Convention: keep the array in its own file (`php/docs/api.php`) and
     * `require` it here — a module with twenty routes would otherwise carry a
     * few hundred lines of prose in the middle of its wiring.
     *
     * Each entry is a plain array (not a value object: at this volume
     * `new RouteDoc(...)` is noise, and the shape is validated by the module's
     * own test):
     *
     *   - `method`      string, uppercase HTTP verb. Must match the registered
     *                   route exactly — one entry per method, so a path served
     *                   as both PUT and PATCH is two entries.
     *   - `pattern`     string, the Slim pattern **verbatim**, placeholders and
     *                   inline regex included (`/tickets/{id:[0-9]+}`). This is
     *                   the join key; a prettified path silently fails to match.
     *   - `summary`     string, one line, imperative or nominal German. Shown
     *                   collapsed, so it must stand on its own.
     *   - `description` string, optional. Longer prose: side effects, rate
     *                   limits, ordering guarantees, what makes it 404.
     *   - `tag`         string, optional. Sub-heading within the module, for
     *                   modules whose surface splits into obvious areas
     *                   ("Öffentlich" / "Verwaltung"). Free text.
     *   - `auth`        one of `public` (no principal needed), `session` (any
     *                   authenticated user), `permission` (see below), `admin`
     *                   (`isAdmin()` only) or `token` (a shared secret, not a
     *                   user session). Defaults to `permission` when a
     *                   `permission` is given, else `public`.
     *   - `permission`  string, optional. The permission id the handler checks.
     *                   Must exist in this module's {@see Module::permissions()}.
     *   - `params`      list, optional. Each: `in` (`path`|`query`|`body`|`header`),
     *                   `name`, `type` (free text: `int`, `string`, `de|en`, …),
     *                   `required` (bool, default false), `description`.
     *   - `responses`   list, optional. Each: `status` (int), `description`,
     *                   `example` (string, optional — a short JSON snippet).
     *
     * @return list<array{
     *   method: string,
     *   pattern: string,
     *   summary: string,
     *   description?: string,
     *   tag?: string,
     *   auth?: 'public'|'session'|'permission'|'admin'|'token',
     *   permission?: string,
     *   params?: list<array{in: 'path'|'query'|'body'|'header', name: string, type: string, required?: bool, description?: string}>,
     *   responses?: list<array{status: int, description: string, example?: string}>
     * }>
     */
    public function apiDocs(): array;
}
