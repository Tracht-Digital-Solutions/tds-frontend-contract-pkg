/**
 * Astro-side glue for the panel extension contract.
 *
 * The host product (`core-frontend`, built as the admin OR customer
 * target) spreads `frontendHost({ extensions: [...] })` into its Astro `integrations`.
 * At build time it:
 *   1. composes the manifests ({@link composeExtensions}) — failing the build
 *      loudly on a conflict / missing-dep,
 *   2. `injectRoute()`s every contributed route, and
 *   3. exposes three virtual modules the shell imports:
 *        - `virtual:panel-registry`  — the flattened {@link ComposedRegistry}
 *          as data (nav, permissions, i18n, routes + widget/settings metadata).
 *        - `virtual:panel-widgets`   — the widgets with a real, statically
 *          imported `Component`, so the Dashboard host can render them in a loop.
 *        - `virtual:panel-settings`  — ditto for the settings sections.
 *
 * Why two shapes: Astro can't hydrate a component named only by a runtime
 * string. So for the slots that render components (widgets, settings) we
 * *generate* module code containing real `import` statements Vite resolves; the
 * `island` specifier in a manifest points to that component's entrypoint (an
 * `.astro` component, which may itself embed a hydrated React island). Nav
 * entries are plain links, so they stay data-only.
 *
 * Composition happens at build time — no runtime plugin loading, no
 * `output: "server"`, one static `dist/` per product.
 *
 * NB: we model Astro's integration shape structurally ({@link
 * AstroIntegrationLike}) instead of importing `astro`, so `frontend-contract` stays
 * dependency-free and builds in isolation; the object is assignment-compatible
 * with the real `AstroIntegration`.
 */

import { mkdirSync, writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { composeExtensions } from "./registry.js";
import type { ComposedRegistry, ExtensionManifest, SettingsPanel, WidgetManifest } from "./types.js";

const MODULES = {
  registry: "virtual:panel-registry",
  widgets: "virtual:panel-widgets",
  settings: "virtual:panel-settings",
} as const;

/** Minimal structural mirror of `astro`'s `AstroIntegration` (build hooks we use). */
export interface AstroIntegrationLike {
  name: string;
  hooks: {
    "astro:config:setup"?: (options: {
      /** Resolved config so far — we only read `root` (to place generated wrappers). */
      config: { root: URL };
      injectRoute: (route: { pattern: string; entrypoint: string; prerender?: boolean }) => void;
      updateConfig: (config: Record<string, unknown>) => void;
      logger: { info: (msg: string) => void; warn: (msg: string) => void };
    }) => void | Promise<void>;
  };
}

export interface FrontendHostOptions {
  /** The enabled extensions for this product build. */
  extensions: ExtensionManifest[];
  /**
   * Import specifier of the host shell `Layout` (an `.astro` component taking a
   * `title` prop + a default slot). When set, every extension route is injected
   * WRAPPED in this Layout so it renders the full panel chrome (head/CSS/nav) —
   * not a bare `<section>` fragment. Products pass the host's Layout, e.g.
   * `"@tracht-digital-solutions/tds-core-frontend/src/layouts/Layout.astro"`.
   *
   * Omitted → routes are injected raw (legacy behaviour; the page must supply
   * its own `<html>`). Base pages injected by `coreFrontendBase()` always wrap
   * themselves, so this only affects extension-contributed routes.
   */
  layout?: string;
}

/**
 * Build the host integration. Composition happens once, up front, so a
 * conflicting or unsatisfied extension set fails the build immediately with a
 * clear message rather than producing a half-wired panel.
 */
export function frontendHost(options: FrontendHostOptions): AstroIntegrationLike {
  const registry: ComposedRegistry = composeExtensions(options.extensions);

  // Map each route pattern → its nav label, so a wrapped page gets a real
  // <title> (falls back to the extension chrome default when a route has no nav).
  const navLabel = new Map(registry.nav.map((n) => [n.href, n.label]));

  return {
    name: "panel-host",
    hooks: {
      "astro:config:setup": ({ config, injectRoute, updateConfig, logger }) => {
        // When a Layout is supplied, we generate one thin wrapper .astro per
        // extension route (importing the Layout + the extension page) and inject
        // THAT — so the extension page renders inside the full panel chrome
        // instead of as a bare fragment with no <head>/CSS/nav. The wrappers are
        // build artifacts under the product's node_modules cache dir.
        let routesDir: URL | undefined;
        if (options.layout) {
          routesDir = new URL("node_modules/.tds-panel/routes/", config.root);
          mkdirSync(routesDir, { recursive: true });
        }

        for (const route of registry.routes) {
          if (options.layout && routesDir) {
            const slug = route.pattern.replace(/[^a-z0-9]+/gi, "_").replace(/^_+|_+$/g, "") || "index";
            const file = new URL(`${slug}.astro`, routesDir);
            const title = navLabel.get(route.pattern) ?? "Panel";
            writeFileSync(file, wrapperSource(options.layout, route.entrypoint, title));
            injectRoute({ pattern: route.pattern, entrypoint: fileURLToPath(file) });
          } else {
            injectRoute({ pattern: route.pattern, entrypoint: route.entrypoint });
          }
        }

        updateConfig({ vite: { plugins: [frontendRegistryVitePlugin(registry)] } });
        logger.info(
          `panel-host: ${registry.order.length} extension(s) [${registry.order.join(", ")}], ` +
            `${registry.routes.length} route(s)${options.layout ? " (Layout-wrapped)" : ""}, ` +
            `${registry.widgets.length} widget(s), ${registry.settings.length} settings panel(s)`,
        );
      },
    },
  };
}

/**
 * Source of a generated route wrapper: import the host Layout + the extension's
 * page component and render the page inside the Layout's default slot. The page
 * keeps rendering its own `<section>` + hydrated islands; the Layout supplies
 * the `<html>`/`<head>` (CSS, fonts, auth-gate) + nav chrome around it.
 */
function wrapperSource(layout: string, entrypoint: string, title: string): string {
  return (
    `---\n` +
    `import Layout from ${JSON.stringify(layout)};\n` +
    `import Page from ${JSON.stringify(entrypoint)};\n` +
    `---\n` +
    `<Layout title={${JSON.stringify(title)}}>\n` +
    `  <Page />\n` +
    `</Layout>\n`
  );
}

/** Minimal structural mirror of a Vite plugin (the two hooks we use). */
interface VitePluginLike {
  name: string;
  resolveId(id: string): string | undefined;
  load(id: string): string | undefined;
}

/** Serves the three virtual modules the host imports. */
function frontendRegistryVitePlugin(registry: ComposedRegistry): VitePluginLike {
  const resolved = new Map<string, string>(
    Object.values(MODULES).map((id) => [id, "\0" + id]),
  );

  return {
    name: "panel-registry",
    resolveId(id) {
      return resolved.get(id);
    },
    load(id) {
      if (id === resolved.get(MODULES.registry)) {
        return `export const registry = ${JSON.stringify(registry)};\n`;
      }
      if (id === resolved.get(MODULES.widgets)) {
        return generateComponentModule("widgets", registry.widgets);
      }
      if (id === resolved.get(MODULES.settings)) {
        return generateComponentModule("settings", registry.settings);
      }
      return undefined;
    },
  };
}

/**
 * Generate a module that statically imports each slot item's `island`
 * component and exports the metadata + the resolved `Component`. Real `import`
 * statements are what let Astro render + hydrate the components in a loop.
 */
function generateComponentModule(
  exportName: "widgets" | "settings",
  items: readonly (WidgetManifest | SettingsPanel)[],
): string {
  const imports: string[] = [];
  const entries: string[] = [];
  items.forEach((item, index) => {
    const local = `__C${index}`;
    imports.push(`import ${local} from ${JSON.stringify(item.island)};`);
    // Spread the metadata (JSON-safe), then attach the imported component.
    entries.push(`  { ...${JSON.stringify(item)}, Component: ${local} }`);
  });
  return (
    imports.join("\n") +
    `\nexport const ${exportName} = [\n${entries.join(",\n")}\n];\n`
  );
}
