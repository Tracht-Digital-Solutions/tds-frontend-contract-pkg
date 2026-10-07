# AGENTS.md — tds-frontend-contract-pkg

The **SDK for the base-frontend + extensions split**. It defines *how* a base frontend
composes extensions and contains no features: no routes, no UI, no database. Two halves in
one repo, mirror images on purpose:

- **TypeScript** (`src/`, npm `@tracht-digital-solutions/tds-frontend-contract`):
  `ExtensionManifest`, `composeExtensions`, and the `frontendHost` Astro integration (`./astro`).
- **PHP** (`php/src/`, Composer `tracht-digital-solutions/tds-frontend-contract`): the `Module`
  interface, `ModuleRegistry` and the core-service interfaces modules consume.

Every extension, both panel products, `tds-core-frontend-pkg` and `tds-core-frontend-api`
depend on it.

## Commands

```bash
npm install --no-package-lock   # never npm ci
npm run build                   # tsup → dual ESM + CJS
npm run type-check              # tsc --noEmit, must be 0 errors
npm run test:run                # vitest
composer install && composer test   # phpunit (ModuleRegistry)
```

## Hard rules

- **Stable at 1.x with additive minors only.** Consumers pin `^1.x`; a breaking change needs 2.0.0 and a coordinated migration.
- Change one half's shape → change its twin (ids, permission ids, settings keys mean the same on both sides).
- New behaviour for modules is an **optional capability** (`instanceof`), never a new method on an existing interface.
- Stay dependency-light: no `astro` dependency in TS; only `slim/slim` in PHP.
- The `virtual:panel-*` aliases keep resolving until a deliberate 2.0.0.
- Don't hand-bump `version`; `release.yml` bumps `package.json` and `composer.json` together.

## Topic files

| File | Read before |
|---|---|
| [docs/agents/architecture.md](docs/agents/architecture.md) | Changing composition, slots, the Astro host or layout wrapping |
| [docs/agents/backend-services.md](docs/agents/backend-services.md) | Changing PHP interfaces, core services or optional capabilities |
| [docs/agents/testing.md](docs/agents/testing.md) | Writing or changing tests, or the toolchain |
| [docs/agents/release.md](docs/agents/release.md) | Releasing or reconciling versions |

Workspace rules: `../CLAUDE.md`. Cross-repo state: `../MIGRATION-STATUS.md`.
