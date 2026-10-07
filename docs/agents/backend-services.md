# Backend services and optional capabilities

`Module::register(App $app)` gets the Slim app, whose DI container the base populates. Modules
resolve services via `$app->getContainer()->get(...)` and never re-implement auth, email or DB
config.

## Core services

| Service | Purpose |
|---|---|
| `UserContext` | Authenticated principal from the verified JWT: `userId`, `email`, `isAdmin`, `permissions`, `has`, `activeCompanyId`. Anonymous → `isAuthenticated()` false |
| `PDO` | Shared DB connection |
| `Mailer` + `Email` | Core SMTP sender; unconfigured → no-op (`isConfigured()` false) |
| `SettingsStore` | Runtime settings, DB first with env fallback |
| `ModuleHttp` (trait, 1.12) | `json()`, `require($user, $permission)`, `requireAdmin($user)` for route closures; `use ModuleHttp;`, never copy them |
| `Stripe\StripeApi` (1.13) | The platform's one Stripe connection, configured under Einstellungen → Zahlungen. A module key may override it (`new CurlStripeApi($key)`). Verify webhooks with `Stripe\StripeWebhook`; implement `Stripe\StripeWebhookSource` so the panel lists the endpoint |
| `SiteCache` + `CacheEvent` (1.10) | Tell a public site to re-render pages affected by a content change |
| `ReportingSiteCache`, `CacheResult` | Additive reporting variant: a truthful result separate from the save outcome |
| `SiteConnections`, `ConnectedSiteCache`, `SiteConnection`, `SitePairing` | Core-owned public-site connections for CMS resources: pairing invitations (short-lived, single-use), connection state (`needs_pairing`, `pending`, `connected`) and cache refresh per connected resource |
| `SiteKeys` (1.9) | Site-key verification service; see below |

**Adding a method to `UserContext` is breaking for implementers** (the core and every test double),
not for callers. Prefer an optional capability.

### `SiteCache` events name content, never URLs

Public sites run Astro SSR behind a file-backed page cache. An event is
`{type:'post', id:'slug', lang:'de'}`: only the site knows its route table, and one post affects
index, category, tag, author, archive and feed pages whose English routes aren't prefixes of the
German ones. **It never throws**; a site that is down or unconfigured must not turn "save" into an
error.

## Optional capabilities (opt-in via `instanceof`)

Optional means a module that doesn't implement it stays valid, which keeps each addition a minor.

### `NotificationSource` (1.6)

Feeds the panel's live notifications. The shell polls **one** endpoint (`GET /me/notifications`);
the base hands each source its own opaque cursor and merges items.

- `$cursor === null` is the first call: return the cursor, **no items** (otherwise a new tab toasts
  the backlog).
- **RBAC lives in the source.** No permission ⇒ `items: []` but still the cursor.
- **Never throw**; one broken source takes down the whole feed.
- TS twin: `NotificationItem` / `NotificationFeed` in `src/types.ts`. No manifest slot: joining the
  feed is a backend decision, which keeps it at one poll.

### `ApiDocSource` (1.7)

Describes routes for the admin API reference (`GET /wiki.json`).

- Introspection stays authoritative: the base reads Slim's `RouteCollector` and left-joins docs by
  `"<METHOD> <pattern>"`. Undocumented routes show as `documented: false`; orphan docs are reported.
- `pattern` must be the Slim pattern **verbatim**, inline regex included.
- Entries are plain arrays kept in `php/docs/api.php`. Each module ships a parity test.

### `MultiCompanyContext` (1.8)

The principal's full membership list, for naming companies and switching. Data scoping still uses
`UserContext::activeCompanyId()`.

```php
$ids = $user instanceof MultiCompanyContext ? $user->companyIds() : [];
```

Membership is not permission; callers still check `has()`. An admin returns `[]`.

### `SiteKeyProtected` + `SiteKeys` (1.9)

Site keys bind a public site (landing page, blog, tools, auth, or custom) to the API.

- **`SiteKeys`** is a service bound by the base. Resolve it null-safely
  (`$c->has(SiteKeys::class)` is fine for this interface). `verify()` returns a `SiteKeyIdentity`
  or null; `enforcement()` is `off` / `warn` / `enforce` (`warn` serves and counts, as a migration
  path).
- **`SiteKeyIdentity`** is a value object so a route knows *which* site presented the key. Never
  trust a `site` field sent next to the key. It never carries the key itself.
- **`SiteKeyProtected`**: a module declares which of its routes are public site reads; the base
  middleware protects exactly those. The base carries no coded path list.

Rules:

- **Prefixes, not patterns** (the middleware runs before routing). Never declare `/content`; it
  covers other modules' routes. `siteKeyRoutes()` normalises trailing slashes and deduplicates.
- **Never declare an admin route**; `ModuleRegistry::siteKeyRoutes()` throws on `/admin`.
- **Never declare a route a visitor's browser calls** (contact form, live chat, account menu).

`ModuleRegistry::routeOwners()` attributes routes to modules by reading the collector before and
after each `register()`. Ownership can't be recovered afterwards; a route missing from the map
belongs to the base.
