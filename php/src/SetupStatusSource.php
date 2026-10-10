<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

/**
 * Optional {@see Module} capability: report which of the module's functions are
 * not set up yet, for the panel's Einrichtungsassistent (`GET /me/setup-status`
 * in the base API, page `/einrichtung` in the panels).
 *
 * The base merges every source and stores, per user, which items were put off
 * until the next sign-in ("Später") or ignored. A module therefore only answers
 * "what is the state", never "should the user see it".
 *
 * ### Unconfigured is silent everywhere else
 *
 * A missing SMTP DSN, Amazon key or webhook secret is not an error anywhere: the
 * feature simply does nothing. That is exactly why each module reports it
 * here — the wizard is the one place where "nothing happens" becomes visible.
 *
 * ### Rules
 *
 * - **Never return a secret.** State whether it is set, nothing more.
 * - **Never throw.** One broken source must not take the wizard down; report
 *   what can be determined and skip the rest.
 * - **RBAC lives here.** The base only asks admins; a source may still return
 *   less for a user without the module's settings permission.
 * - **Cheap.** Settings reads and `isConfigured()` checks, no network calls —
 *   the panel asks on every page load for the banner.
 */
interface SetupStatusSource
{
    /**
     * The module's setup items. Each is a plain array:
     *   - `id`          string, globally unique and STABLE, `"<module-id>:<key>"`
     *                   — the user's "Später"/"Ignorieren" choice is stored by it.
     *   - `module`      string, the module id.
     *   - `title`       string, German, names the function ("Amazon-Partnerprogramm").
     *   - `description` string, German, one or two sentences: what does not work
     *                   until this is done.
     *   - `state`       `ok|missing|partial`.
     *   - `level`       `required|recommended|optional` — `required` means a core
     *                   function of the module is dead without it.
     *   - `href`        string, panel path where it is set up, usually
     *                   `/einstellungen#<settings-panel-id>`.
     *
     * Report `ok` items too: the wizard shows them as done, which is how the
     * operator sees progress.
     *
     * @return list<array<string,string>>
     */
    public function setupItems(UserContext $user): array;
}
