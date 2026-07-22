# frontend-contract

The **frontend extension contract** — the SDK that lets a *base frontend* discover and
compose *extensions* at **build time**. It is the foundation of the split of
`tds-admin` into a slim **base** (users, wiki, dashboard host, UI shell, API
kernel, email) plus feature **extensions** (time tracker, blog-CMS, website-CMS,
contact tickets, support tickets, …), each in its own repo.

Two halves ship from this one repo:

| Half | Package | Consumed by |
|---|---|---|
| **Frontend** (TypeScript) | `@tracht-digital-solutions/tds-frontend-contract` (GitHub Packages) | `core-frontend` + every extension's frontend package |
| **Backend** (PHP) | `tracht-digital-solutions/tds-frontend-contract` (Composer) | `core-frontend-api` + every extension's backend package |

## Why build-time composition

The frontends are Astro `output: "static"` with **no Node runtime on production**
(Plesk), and the API runs every backend **in one PHP-FPM process** (the gateway's
`inprocess` model). So there is no runtime plugin loader: the base imports each
extension's manifest/module and folds it into **one** static `dist/` and **one**
in-process API. Enabling an extension = add a dependency + one line in the
product's config. This is the same pattern `tds-shared-pkg` already uses, generalised
from "shared design" to "mountable features".

## Frontend contract (TypeScript)

An extension exports an `ExtensionManifest` describing what it contributes:

```ts
import { defineExtension } from "@tracht-digital-solutions/tds-frontend-contract";

export default defineExtension({
  id: "time-tracker",
  name: "Zeiterfassung",
  version: "0.1.0",
  permissions: [{ id: "time:read", label: "Zeiten ansehen" }],
  nav: [{ id: "time", label: "Zeiterfassung", href: "/time", permission: "time:read" }],
  widgets: [{ id: "time-week", title: "Diese Woche", island: "ext-time/Week", dataEndpoint: "/time/summary" }],
  routes: [{ pattern: "/time", entrypoint: "ext-time/pages/Index.astro" }],
  settings: [{ id: "time", label: "Zeiterfassung", island: "ext-time/Settings" }],
  i18n: { de: { "time.title": "Zeiterfassung" }, en: { "time.title": "Time tracking" } },
});
```

The **contribution slots**: `permissions`, `nav`, `widgets` (dashboard cards —
the base Dashboard is a host that renders the enabled + permitted widgets and
persists per-user layout), `routes`, `settings`, `i18n`.

The product host composes them in its `astro.config.mjs`:

```ts
import { frontendHost } from "@tracht-digital-solutions/tds-frontend-contract/astro";
import timeTracker from "@tracht-digital-solutions/tds-ext-time-tracker";
// ...
export default defineConfig({
  integrations: [
    frontendHost({
      extensions: [timeTracker /*, ...*/],
      // Wrap every extension route in the host shell Layout (head/CSS/nav).
      layout: "@tracht-digital-solutions/tds-core-frontend/src/layouts/Layout.astro",
    }),
  ],
});
```

`frontendHost` composes the manifests up front (failing the build loudly on a
conflict / missing dependency), `injectRoute()`s every route, and exposes the
flattened registry as the virtual module `virtual:frontend-registry` for the shell
to render nav / widgets / settings from.

**Always pass `layout`.** An extension `pages/*.astro` renders only its content
(a `<section>` + islands), not a full `<html>` document. With `layout` set,
`frontendHost` generates a per-route wrapper that renders the page inside that shell
Layout, so it gets the frontend chrome (head, CSS, fonts, auth-gate, nav). Omit it
and every extension page ships as a bare, unstyled fragment.

## Backend contract (PHP)

An extension ships a `Module` (extend `AbstractModule`):

```php
final class TimeTrackerModule extends AbstractModule
{
    public function id(): string { return 'time-tracker'; }
    public function register(App $app): void { /* $app->get('/time/summary', ...) */ }
    public function migrations(): array { return [__DIR__ . '/../db/migrations']; }
    public function permissions(): array { return [new PermissionDef('time:read', 'Zeiten ansehen')]; }
}
```

The base API builds a `ModuleRegistry` from the enabled modules and:
`registerAll($app)` mounts routes in dependency order, `migrationPaths()` feeds
the in-process auto-migrator, `permissions()` / `settings()` yield the merged
catalog. **Migration class names must be globally unique** across every module
(the in-process migrator `include`s them all into one process — a reused class
name is a fatal redeclaration; prefix with the module id).

## Develop

```bash
# TypeScript
npm install
npm run build        # tsup → dual ESM+CJS in dist/
npm run type-check   # tsc --noEmit
npm run test:run     # vitest

# PHP
composer install
composer test        # phpunit
```

## Versioning

Semver. Bump on every change (`npm version <patch|minor|major>` mirrors into
`composer.json`'s `version` field — keep the two in lockstep). Publishing:
`npm publish` → GitHub Packages; the Composer package is consumed via a VCS/path
repository entry in each backend's `composer.json`.
