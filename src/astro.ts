/**
 * Astro-side glue for the panel extension contract.
 *
 * The host product (`core-panel-frontend` built as the admin OR customer
 * target) spreads `panelHost({ extensions: [...] })` into its Astro `integrations`.
 * At build time it:
 *   1. composes the manifests ({@link composeExtensions}) — failing the build
 *      loudly on a conflict/missing-dep,
 *   2. `injectRoute()`s every contributed route, and
 *   3. exposes the flattened {@link ComposedRegistry} as a virtual module
 *      (`virtual:panel-registry`) the shell imports for nav / widgets / settings.
 *
 * This keeps composition at build time — no runtime plugin loading, no
 * `output: "server"`, one static `dist/` per product. See tds-shared's
 * `./astro` export for the precedent of shipping build config from a package.
 *
 * NB: we deliberately model Astro's integration shape structurally
 * ({@link AstroIntegrationLike}) instead of importing `astro`, so
 * `panel-contract` stays dependency-free and builds in isolation. The host
 * passes the real integration object straight to Astro; the structural type is
 * assignment-compatible.
 */

import { composeExtensions } from "./registry.js";
import type { ComposedRegistry, ExtensionManifest } from "./types.js";

const VIRTUAL_ID = "virtual:panel-registry";
const RESOLVED_VIRTUAL_ID = "\0" + VIRTUAL_ID;

/** Minimal structural mirror of `astro`'s `AstroIntegration` (build hooks we use). */
export interface AstroIntegrationLike {
  name: string;
  hooks: {
    "astro:config:setup"?: (options: {
      injectRoute: (route: { pattern: string; entrypoint: string; prerender?: boolean }) => void;
      updateConfig: (config: Record<string, unknown>) => void;
      logger: { info: (msg: string) => void; warn: (msg: string) => void };
    }) => void | Promise<void>;
  };
}

export interface PanelHostOptions {
  /** The enabled extensions for this product build. */
  extensions: ExtensionManifest[];
}

/**
 * Build the host integration. Composition happens once, up front, so a
 * conflicting or unsatisfied extension set fails the build immediately with a
 * clear message rather than producing a half-wired panel.
 */
export function panelHost(options: PanelHostOptions): AstroIntegrationLike {
  const registry: ComposedRegistry = composeExtensions(options.extensions);

  return {
    name: "panel-host",
    hooks: {
      "astro:config:setup": ({ injectRoute, updateConfig, logger }) => {
        for (const route of registry.routes) {
          injectRoute({ pattern: route.pattern, entrypoint: route.entrypoint });
        }
        updateConfig({
          vite: { plugins: [panelRegistryVitePlugin(registry)] },
        });
        logger.info(
          `panel-host: ${registry.order.length} extension(s) [${registry.order.join(", ")}], ` +
            `${registry.routes.length} route(s), ${registry.widgets.length} widget(s)`,
        );
      },
    },
  };
}

/** Minimal structural mirror of a Vite plugin (the two hooks we use). */
interface VitePluginLike {
  name: string;
  resolveId(id: string): string | undefined;
  load(id: string): string | undefined;
}

/**
 * Serves the composed registry as `virtual:panel-registry`, so the shell can
 * `import { registry } from "virtual:panel-registry"`. Island/entrypoint fields
 * stay strings the host resolves — the registry itself carries no live imports.
 */
function panelRegistryVitePlugin(registry: ComposedRegistry): VitePluginLike {
  return {
    name: "panel-registry",
    resolveId(id) {
      return id === VIRTUAL_ID ? RESOLVED_VIRTUAL_ID : undefined;
    },
    load(id) {
      if (id !== RESOLVED_VIRTUAL_ID) return undefined;
      return `export const registry = ${JSON.stringify(registry)};\n`;
    },
  };
}
