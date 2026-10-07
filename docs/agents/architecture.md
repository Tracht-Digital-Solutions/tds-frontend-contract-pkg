# Architecture

## Build-time composition, not runtime plugins

There is no runtime plugin loader. A product imports each extension's manifest and folds it
into one build; the API imports each extension's `Module` and runs them in one in-process PHP
app. This generalises the build-time package pattern of `tds-shared-pkg`.

## Contribution slots

| Side | Slots |
|---|---|
| Frontend (`ExtensionManifest`) | `permissions`, `nav`, `widgets`, `routes`, `settings`, `i18n` |
| Backend (`Module`) | `register()` (routes), `migrations()`, `permissions()`, `settings()`, `dependsOn()` |

- **Widgets are a first-class slot.** The base dashboard renders enabled and permitted widgets
  and persists a per-user layout.
- **`dependsOn` drives load order** (topological). A missing dependency or a cycle throws, on both
  sides. "Extension extends extension" is expressed only via `dependsOn` plus targeting another
  extension's nav `group`.

## No namespacing across extensions

Everything lands in one build, so a duplicate id (permission, nav, widget, settings, route
pattern, extension) is a hard error in `composeExtensions` / `ModuleRegistry`. It is the frontend
twin of the Phinx rule: **migration class names must be globally unique** (the in-process migrator
includes them all into one PHP process; a reused class is a fatal redeclaration). Prefix every
migration with the module id.

## Extension routes are wrapped in the host layout

An extension's `pages/*.astro` renders only its content (a `<section>` plus islands), never a full
`<html>` document. `frontendHost({ layout })` takes the host shell layout
(`…/tds-core-frontend/src/layouts/Layout.astro`), generates one thin wrapper per route
(`<Layout><Page/></Layout>` under `node_modules/.tds-frontend/routes/`) and injects that.

- **Omit `layout` and every extension page ships as a bare, unstyled fragment** with no `<head>`.
- Base pages (injected by `coreFrontendBase()`) import the layout themselves.
- The wrapper assumes static extension routes (no per-route `getStaticPaths`).

## Virtual modules

`virtual:frontend-{registry,widgets,settings}`. The old `virtual:panel-*` spellings are public (a
host writes them in an `import`) and **must keep resolving** until a deliberate 2.0.0. Both
spellings resolve to the same internal id, so a host mixing them gets one module instance.
Widgets and settings are served with real static `import`s; Astro can't hydrate a component named
by a runtime string. A leftover `node_modules/.tds-panel/routes/` directory from before the rename
is harmless.

## Dependency-light

- TS: pure, no `astro` dependency. The Astro integration is modelled structurally
  (`AstroIntegrationLike`) so the package builds in isolation.
- PHP: only `slim/slim`. Don't pull feature dependencies in here.

## Copy

Labels are German editable copy and live with the contract or extension, never inline in a page.
