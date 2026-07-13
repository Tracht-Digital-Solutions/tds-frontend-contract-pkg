# AGENTS.md — panel-contract

Authoritative architecture/gotcha doc for this repo. Read before non-trivial changes.

## What this is

`panel-contract` is the **SDK for the base-panel + extensions split** of the TDS
admin platform. It defines *how* a base panel composes extensions — nothing more.
It contains **no features**: no routes, no UI, no DB. Two halves, one repo:

- **TypeScript** (`src/`, published to GitHub Packages as
  `@tracht-digital-solutions/panel-contract`) — the frontend `ExtensionManifest`
  + `composeExtensions` + the `panelHost` Astro integration (`./astro` export).
- **PHP** (`php/src/`, Composer `tracht-digital-solutions/panel-contract`) — the
  backend `Module` interface + `ModuleRegistry`.

The two halves are **mirror images on purpose** (like the existing Zod ↔ PHP
validator duplication): a permission `id`, a settings `key`, an extension `id`
mean the same thing on both sides. Change one shape → change its twin.

## The composition model (why it looks like this)

Build-time, not runtime. The panels are Astro `output: "static"` (no Node on
prod) and the API is one in-process PHP-FPM app (the gateway `inprocess` model),
so there is **no runtime plugin loader**. Composition is: the base imports each
extension's manifest/module and folds it into one static build / one in-process
app. This is the generalisation of the `tds-shared` build-time package pattern.

## Contribution slots

Frontend (`ExtensionManifest`): `permissions`, `nav`, `widgets`, `routes`,
`settings`, `i18n`. Backend (`Module`): `register()` (routes), `migrations()`,
`permissions()`, `settings()`, `dependsOn()`.

**Widgets are a first-class slot** — blog-CMS, website-CMS, both ticket systems
and the time tracker all contribute dashboard cards through it; the base
Dashboard is the host (renders enabled + permitted widgets, persists per-user
layout = the "user-based dashboard").

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
- **`panel-contract` stays dependency-light.** The TS side is pure (no `astro`
  dependency — the Astro integration is modelled structurally via
  `AstroIntegrationLike` so the package builds in isolation). The PHP side only
  depends on `slim/slim` (the framework every backend already uses). Don't pull
  feature deps in here.
- **Labels are German editable copy.** They live with the contract/extension, per
  the TDS convention — never inline them in a page.

## Commands

```bash
npm run build        # tsup → dual ESM+CJS
npm run type-check   # tsc --noEmit — must be 0 errors
npm run test:run     # vitest (composition helpers)
composer test        # phpunit (ModuleRegistry)
```

## After a change

Update this file + README, and bump the version in **both** `package.json` and
`composer.json` (keep them in lockstep — they are one release). Commit code +
docs + version together.
