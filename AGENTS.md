# AGENTS.md — frontend-contract

Authoritative architecture/gotcha doc for this repo. Read before non-trivial changes.

## What this is

`frontend-contract` is the **SDK for the base-frontend + extensions split** of the TDS
admin platform. It defines *how* a base frontend composes extensions — nothing more.
It contains **no features**: no routes, no UI, no DB. Two halves, one repo:

- **TypeScript** (`src/`, published to GitHub Packages as
  `@tracht-digital-solutions/tds-frontend-contract`) — the frontend `ExtensionManifest`
  + `composeExtensions` + the `frontendHost` Astro integration (`./astro` export).
- **PHP** (`php/src/`, Composer `tracht-digital-solutions/tds-frontend-contract`) — the
  backend `Module` interface + `ModuleRegistry`.

The two halves are **mirror images on purpose** (like the existing Zod ↔ PHP
validator duplication): a permission `id`, a settings `key`, an extension `id`
mean the same thing on both sides. Change one shape → change its twin.

## The composition model (why it looks like this)

Build-time, not runtime. The frontends are Astro `output: "static"` (no Node on
prod) and the API is one in-process PHP-FPM app (the gateway `inprocess` model),
so there is **no runtime plugin loader**. Composition is: the base imports each
extension's manifest/module and folds it into one static build / one in-process
app. This is the generalisation of the `tds-shared-pkg` build-time package pattern.

## Contribution slots

Frontend (`ExtensionManifest`): `permissions`, `nav`, `widgets`, `routes`,
`settings`, `i18n`. Backend (`Module`): `register()` (routes), `migrations()`,
`permissions()`, `settings()`, `dependsOn()`.

**Widgets are a first-class slot** — blog-CMS, website-CMS, both ticket systems
and the time tracker all contribute dashboard cards through it; the base
Dashboard is the host (renders enabled + permitted widgets, persists per-user
layout = the "user-based dashboard").

**Extension routes are wrapped in the host `Layout` (`frontendHost({ layout })`).**
An extension `pages/*.astro` renders only its **content** (a `<section>` + its
islands) — NOT a full `<html>` document. So `frontendHost` must be given the host
shell Layout (`layout: ".../tds-core-frontend/src/layouts/Layout.astro"`);
it then generates one thin wrapper `.astro` per route (`<Layout><Page/></Layout>`,
under `node_modules/.tds-frontend/routes/`) and injects THAT, so the page renders
inside the full frontend chrome (head/CSS/fonts/auth-gate/nav). **Omit `layout` and
every extension page ships as a bare, unstyled fragment** (no `<head>`, no CSS
link) — this was the "admin frontend has no formatting" bug. Base pages (injected by
`coreFrontendBase()`) import the Layout themselves, so they were never affected.
The wrapper approach assumes static extension routes (no per-route
`getStaticPaths`); the current extensions all ship a single static index page.

## Core services for modules (backend)

A `Module::register(App $app)` gets the Slim app, whose **DI container the base
populates** with the services extensions may need. Modules resolve them via
`$app->getContainer()->get(...)` — they never re-implement auth, email, or DB
config:

- **`Mailer`** (+ the `Email` value object) — the core's SMTP sender. Config +
  From identity live in the base; a module only builds an `Email` and sends it.
  Unconfigured SMTP → a no-op mailer (`isConfigured()` false).
- **`UserContext`** — the authenticated principal from the verified JWT
  (`userId`/`email`/`isAdmin`/`permissions`/`has`/`activeCompanyId`). Read it for
  RBAC + tenant scoping + notification recipients; anonymous → `isAuthenticated()`
  false. NB adding a method here is breaking for *implementers* (the core) but not
  for callers (extensions) — bump the core's impls in lockstep.
- **`PDO`** — the shared DB connection (standard class, no contract type).

These interfaces are the shared vocabulary the base implements and modules
consume — the PHP analogue of the shared permission catalog.

### Optional capability: `NotificationSource` (1.6.0)

A module may additionally `implements NotificationSource` to feed the panel's
live notifications. The shell polls **one** endpoint (`GET /me/notifications`)
on every page; the base hands each source its own opaque cursor, merges the
items and encodes the cursors back. Three rules that are easy to get wrong:

- **`$cursor === null` is the first call: return the cursor, no items.**
  Otherwise every freshly opened tab toasts the whole backlog.
- **RBAC lives in the source**, not the base — the base cannot know what an
  event requires. No permission ⇒ `items: []` **but still the cursor**, so
  granting the permission later does not replay the interim.
- **Never throw.** One broken source would take the whole feed down, and with
  it the shell's poll.

The wire shape has a TS twin (`NotificationItem` / `NotificationFeed` in
`src/types.ts`) so the host's poller and the extension islands agree on it. Note
there is deliberately **no manifest slot**: joining the feed is a backend
decision, which is what keeps it at one poll for all modules instead of one
interval per extension on every page.

Being optional is what makes this a MINOR: a module that does not implement it
is unchanged and still valid.

## Gotchas / invariants

- **No namespacing across extensions.** Everything lands in one build, so a
  duplicate id (permission / nav / widget / settings / route pattern) is a hard
  error — `composeExtensions` / `ModuleRegistry` throw. This is the frontend twin
  of the Phinx unique-class-name rule.
- **Migration class names must be globally unique** across every backend module
  (the in-process auto-migrator `include`s them all into one PHP process — a
  reused class name is an uncatchable fatal redeclaration). Prefix every
  migration with the module id.
- **`dependsOn` drives load order** (topological). Missing dep / cycle → throw,
  on both sides. "Extension extends extension" is expressed purely through
  `dependsOn` + targeting another extension's nav `group`.
- **`frontend-contract` stays dependency-light.** The TS side is pure (no `astro`
  dependency — the Astro integration is modelled structurally via
  `AstroIntegrationLike` so the package builds in isolation). The PHP side only
  depends on `slim/slim` (the framework every backend already uses). Don't pull
  feature deps in here.
- **Labels are German editable copy.** They live with the contract/extension, per
  the TDS convention — never inline them in a page.
- **The virtual-module ids are `virtual:frontend-{registry,widgets,settings}`,
  and the old `virtual:panel-*` spellings must keep resolving.** They were the
  last `panel-` names in the SDK (pre-rename) and they are **public** — a host
  writes them in an `import` — so dropping them is a breaking change, and this
  package is stable at **1.x with additive minors only** (`^1.0.0` pins).
  Keeping them as aliases made the rename a *minor*: the host migrates on its
  own schedule instead of every product needing a coordinated release. Both
  spellings resolve to the **same internal id**, so a build that mixes them
  (a migrating host) gets one module instance, not two copies of the registry —
  `src/__tests__/astro.test.ts` asserts exactly that. Remove the aliases only
  in a deliberate 2.0.0.
  - The generated route-wrapper cache moved with it:
    `node_modules/.tds-panel/routes/` → `.tds-frontend/routes/`. It is a build
    artifact, so nothing needs migrating, but an old directory may linger in a
    long-lived local `node_modules` (harmless — CI installs fresh).
- **`vitest.config.ts` pins `include: ["src/**/*.test.ts"]` on purpose.** With
  vitest's default glob, any `.claude/worktrees/*/src/**` checkout in the repo is
  swept into the run, so the suite silently reports *another branch's* tests as
  if they were this package's — including tests for the old virtual-module names.
- **The `version` field is owned by the release workflow** (`npm version <bump>`
  computes from what is committed), so check it against the registry before
  touching it. It had drifted **behind**: the field said `1.4.1` while `1.4.2`
  and `1.4.3` were already published, which made a *patch* release a guaranteed
  409 (`1.4.2` exists) while a minor happened to survive. Reconciled to `1.4.3`.
  `npm view … versions` is the check.

## Commands

```bash
npm run build        # tsup → dual ESM+CJS
npm run type-check   # tsc --noEmit — must be 0 errors
npm run test:run     # vitest, 66 tests (composition + the Astro host)
composer test        # phpunit (ModuleRegistry)
```

## Tests

This package is the SDK **both halves of the platform depend on**, so a weak
test here is a bug in fourteen extensions and two products at once.

- `src/__tests__/registry.test.ts` — the original happy paths.
- `src/__tests__/registry.collisions.test.ts` — the guard, which is the point
  of the whole module. A product build folds every extension into ONE namespace
  with no prefixing, so **each** contribution kind must throw on a duplicate id
  (permission / nav / widget / settings / route) — asserted separately, since
  one shared `Set` for all five would pass a test that only checks routes. Also
  covered: duplicate extension ids, cycles (incl. self-dependency), diamonds,
  missing dependencies, stable `order` sorting with the 100 default, routes
  left deliberately unsorted, and i18n merge precedence (**later wins**, by
  dependency order rather than argument order).
- `src/__tests__/astro.test.ts` — the build-time host integration, previously
  untested. The behaviour that matters most is the **Layout wrapping**: an
  extension page renders only its own `<section>`, so when `layout` is supplied
  the host must inject a generated wrapper and NOT the raw page. Injecting the
  page raw is precisely the "admin panel has no formatting" bug fixed in 1.4.0,
  and both directions are pinned. The wrapper's `<Page />` is asserted to be
  **nested inside** `<Layout>`, not merely present — `<Layout></Layout>` followed
  by `<Page />` contains every expected string and still renders outside the
  chrome. Also: composition failures throw while CONSTRUCTING the integration
  (not inside the hook, which would half-wire the panel), slug derivation for
  the wrapper filenames, and the three virtual modules — including that
  `resolveId` ignores ids it does not own, and that widgets/settings are served
  with a real static `import` (Astro cannot hydrate a component named by a
  runtime string).

`node:fs` is mocked in the astro tests: the wrappers are build artifacts, and
what matters is what would be written and which path gets injected.

Verified by mutation: 47 deliberate breakages introduced, 47 caught.

## After a change

Update this file + README, and bump the version in **both** `package.json` and
`composer.json` (keep them in lockstep — they are one release). Commit code +
docs + version together.

> **But do NOT hand-bump for a normal release.** `release.yml` runs
> `npm version <bump>` over what is committed and writes both files itself, then
> pushes the bump commit + the annotated tag. Bumping by hand first makes the
> workflow skip a version (and, if the field has drifted *behind* the registry, a
> guaranteed 409). Choose the bump on the button instead — this change is a
> **minor** (the optional `NotificationSource` capability). Hand-editing is only
> for reconciling drift, checked against `npm view … versions`.
