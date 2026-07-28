import { describe, expect, it } from "vitest";

import { frontendHost } from "../astro.js";
import type { AstroIntegrationLike } from "../astro.js";
import type { ExtensionManifest } from "../types.js";

/**
 * The virtual-module ids are PUBLIC names — a host writes them in an `import`.
 * They were renamed `virtual:panel-*` → `virtual:frontend-*` (the last
 * `panel-` names in the SDK), and the old spellings must keep resolving:
 * this package is stable at 1.x with additive minors only, so dropping them
 * would silently break any host one version behind at *build* time.
 */

interface VitePluginLike {
  name: string;
  resolveId(id: string): string | undefined;
  load(id: string): string | undefined;
}

const CANONICAL = [
  "virtual:frontend-registry",
  "virtual:frontend-widgets",
  "virtual:frontend-settings",
] as const;

const LEGACY = [
  "virtual:panel-registry",
  "virtual:panel-widgets",
  "virtual:panel-settings",
] as const;

/** Run the integration's setup hook and capture the Vite plugin it registers. */
function setup(extensions: ExtensionManifest[] = []): {
  plugin: VitePluginLike;
  injected: { pattern: string; entrypoint: string }[];
  logs: string[];
  integration: AstroIntegrationLike;
} {
  const integration = frontendHost({ extensions });
  const injected: { pattern: string; entrypoint: string }[] = [];
  const logs: string[] = [];
  let plugin: VitePluginLike | undefined;

  integration.hooks["astro:config:setup"]!({
    config: { root: new URL("file:///tmp/product/") },
    injectRoute: (r) => injected.push({ pattern: r.pattern, entrypoint: r.entrypoint }),
    updateConfig: (cfg) => {
      const vite = cfg.vite as { plugins: VitePluginLike[] } | undefined;
      if (vite?.plugins?.[0]) plugin = vite.plugins[0];
    },
    logger: { info: (m) => logs.push(m), warn: (m) => logs.push(m) },
  });

  if (!plugin) throw new Error("integration registered no vite plugin");
  return { plugin, injected, logs, integration };
}

describe("frontendHost", () => {
  it("is named after the platform, not the pre-rename 'panel'", () => {
    expect(frontendHost({ extensions: [] }).name).toBe("frontend-host");
  });

  it("logs under the renamed prefix", () => {
    const { logs } = setup();
    expect(logs.join("\n")).toContain("frontend-host:");
    expect(logs.join("\n")).not.toContain("panel-host:");
  });
});

describe("virtual module ids", () => {
  it("resolves the canonical virtual:frontend-* ids", () => {
    const { plugin } = setup();
    for (const id of CANONICAL) {
      expect(plugin.resolveId(id), id).toBe("\0" + id);
    }
  });

  it("still resolves the deprecated virtual:panel-* aliases", () => {
    const { plugin } = setup();
    for (const id of LEGACY) {
      expect(plugin.resolveId(id), id).toBeDefined();
    }
  });

  it("maps each alias to the SAME internal id as its canonical name", () => {
    // Two internal ids would mean two module instances — two copies of the
    // registry in one build, which is the bug this asserts against.
    const { plugin } = setup();
    LEGACY.forEach((legacy, i) => {
      expect(plugin.resolveId(legacy), legacy).toBe(plugin.resolveId(CANONICAL[i]!));
    });
  });

  it("serves loadable content for every canonical id", () => {
    const { plugin } = setup();
    for (const id of CANONICAL) {
      const code = plugin.load("\0" + id);
      expect(code, id).toBeTypeOf("string");
      expect(code!.length, id).toBeGreaterThan(0);
    }
  });

  it("serves the same content through an alias as through its canonical id", () => {
    const { plugin } = setup();
    LEGACY.forEach((legacy, i) => {
      const viaAlias = plugin.load(plugin.resolveId(legacy)!);
      const viaCanonical = plugin.load(plugin.resolveId(CANONICAL[i]!)!);
      expect(viaAlias, legacy).toBe(viaCanonical);
    });
  });

  it("ignores unknown ids (leaves them to other plugins)", () => {
    const { plugin } = setup();
    expect(plugin.resolveId("virtual:nope")).toBeUndefined();
    expect(plugin.load("\0virtual:nope")).toBeUndefined();
    // A bare (unprefixed) id must not load — Vite passes the resolved form.
    expect(plugin.load("virtual:frontend-registry")).toBeUndefined();
  });

  it("exposes the composed registry as data under the registry id", () => {
    const { plugin } = setup([
      { id: "time", name: "Time", version: "0.1.0", nav: [{ id: "time", label: "Zeit", href: "/zeit" }] },
    ]);
    const code = plugin.load("\0virtual:frontend-registry")!;
    expect(code).toContain("export const registry =");
    expect(code).toContain('"/zeit"');
  });
});
