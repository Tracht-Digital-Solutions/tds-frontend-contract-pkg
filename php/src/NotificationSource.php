<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

/**
 * Optional {@see Module} capability: contribute events to the panel's live
 * notification feed (`GET /me/notifications` in the base API).
 *
 * The panel shell polls that ONE endpoint on every page and raises a toast per
 * item. Doing it per extension would mean one interval per module on every
 * page; doing it here means a new module joins the feed without the frontend
 * host changing at all.
 *
 * ### The cursor is per module and opaque to the browser
 *
 * The base keeps a map of module-id → cursor, hands each source its own, and
 * encodes the updated map back into the response. A module therefore never has
 * to coordinate its cursor with anyone else's, and a module added later simply
 * has no cursor yet — which is exactly the first-call case below.
 *
 * ### RBAC lives here, not in the base
 *
 * The base cannot know what a given event requires. A source that the principal
 * may not read returns `items: []` — but still returns its cursor, so switching
 * a permission on later does not replay everything that happened meanwhile.
 */
interface NotificationSource
{
    /**
     * New events for $user since $cursor.
     *
     * **$cursor === null means "first call": return the current cursor and NO
     * items.** Otherwise a freshly opened tab would toast the entire backlog —
     * the notification is about something that *just* happened, and a burst of
     * twenty on page load is noise the reader has no way to act on.
     *
     * Implementations must not throw: one broken source must not take the whole
     * feed (and with it the shell's poll) down. Return an empty list instead.
     *
     * An item is a plain array:
     *   - `id`         string, globally unique, `"<module-id>:<local-id>"` —
     *                  also the toast's dedup key, so a repeat counts up
     *                  instead of stacking.
     *   - `module`     string, the module id (lets a list filter its own).
     *   - `kind`       string, e.g. `"contact.new"` — for consumers, not shown.
     *   - `message`    string, ready-to-read plain text. The toast has no title
     *                  and renders text, never HTML, so say it in one line.
     *   - `href`       string, same-document path the toast links to.
     *   - `variant`    `info|success|warning|danger`.
     *   - `created_at` string, ISO-8601 — the base sorts the merged feed by it.
     *
     * @return array{cursor: string, items: list<array<string,mixed>>}
     */
    public function notifications(UserContext $user, ?string $cursor): array;
}
