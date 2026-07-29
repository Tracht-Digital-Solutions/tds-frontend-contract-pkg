import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("node:fs", () => ({
  mkdirSync: vi.fn(),
  writeFileSync: vi.fn(),
}));

import { mkdirSync, writeFileSync } from "node:fs";
import { join } from "node:path";
import { pathToFileURL } from "node:url";
import { frontendHost } from "../astro.js";
import { defineExtension } from "../registry.js";
import type { ExtensionManifest } from "../types.js";

/**
 * The Astro host integration — the build-time half of the contract.
 *
 * The behaviour that matters most is the **Layout wrapping**. An extension page
 * renders only its own `<section>`; it has no `<html>`, no `<head>`, no CSS and
 * no auth gate. When `layout` is supplied the host generates a thin wrapper
 * `.astro` per route and injects THAT, so the page lands inside the panel
 * chrome. Omitting `layout` injects the page raw — which is exactly the bug
 * that once shipped every extension page as an unstyled fragment (fixed in
 * contract 1.4.0). Both directions are pinned here.
 *
 * `node:fs` is mocked: the generated wrappers are build artifacts, and what
 * matters is WHAT would be written and WHICH path gets injected.
 */

const base = (over: Partial<ExtensionManifest> & Pick<ExtensionManifest, "id">): ExtensionManifest => ({
  name: over.name ?? over.id,
  version: over.version ?? "0.1.0",
  ...over,
});

/** A real absolute root: fileURLToPath needs a drive letter on Windows. */
const ROOT = new URL(pathToFileURL(join(process.cwd(), "product")).href + "/");

const LAYOUT = "@tracht-digital-solutions/tds-core-frontend/src/layouts/Layout.astro";

/** A capture of everything the integration did during `astro:config:setup`. */
function runSetup(host: ReturnType<typeof frontendHost>) {
  const injected: Array<{ pattern: string; entrypoint: string }> = [];
  const configs: Record<string, unknown>[] = [];
  const logs: string[] = [];
  const warns: string[] = [];
  host.hooks["astro:config:setup"]!({
    config: { root: ROOT },
    injectRoute: (r) => injected.push({ pattern: r.pattern, entrypoint: r.entrypoint }),
    updateConfig: (c) => configs.push(c),
    logger: { info: (m) => logs.push(m), warn: (m) => warns.push(m) },
  });
  return { injected, configs, logs, warns };
}

/** The Vite plugin the integration hands to Astro via updateConfig. */
function plugin(configs: Record<string, unknown>[]) {
  const vite = configs[0]!.vite as { plugins: Array<{ name: string; resolveId(id: string): string | undefined; load(id: string): string | undefined }> };
  return vite.plugins[0]!;
}

const TIME = defineExtension(
  base({
    id: "time-tracker",
    nav: [{ id: "time", label: "Zeiterfassung", href: "/time", order: 20 }],
    widgets: [{ id: "time-week", title: "Diese Woche", island: "ext-time/widgets/Week.astro" }],
    settings: [{ id: "time", label: "Zeiten", island: "ext-time/islands/Settings.astro" }],
    routes: [{ pattern: "/time", entrypoint: "ext-time/pages/Index.astro" }],
  }),
);

beforeEach(() => {
  vi.mocked(mkdirSync).mockClear();
  vi.mocked(writeFileSync).mockClear();
});

describe("composition failures fail the BUILD, not the page", () => {
  it("throws while constructing the integration, before any hook runs", () => {
    // Composing inside the hook would half-wire the panel and surface the
    // conflict as a mysterious runtime gap instead of a build error.
    const a = defineExtension(base({ id: "a", routes: [{ pattern: "/x", entrypoint: "a/x" }] }));
    const b = defineExtension(base({ id: "b", routes: [{ pattern: "/x", entrypoint: "b/x" }] }));
    expect(() => frontendHost({ extensions: [a, b] })).toThrow(/Conflicting route id/);
  });

  it("throws on an unsatisfied dependency", () => {
    const dep = defineExtension(base({ id: "reports", dependsOn: ["time-tracker"] }));
    expect(() => frontendHost({ extensions: [dep] })).toThrow(/depends on "time-tracker"/);
  });

  it("composes an empty extension set without complaint", () => {
    const host = frontendHost({ extensions: [] });
    const { injected } = runSetup(host);
    expect(injected).toEqual([]);
  });
});

describe("route injection WITHOUT a layout", () => {
  it("injects the extension entrypoint raw", () => {
    const { injected } = runSetup(frontendHost({ extensions: [TIME] }));
    expect(injected).toEqual([{ pattern: "/time", entrypoint: "ext-time/pages/Index.astro" }]);
  });

  it("writes no wrapper files at all", () => {
    runSetup(frontendHost({ extensions: [TIME] }));
    expect(mkdirSync).not.toHaveBeenCalled();
    expect(writeFileSync).not.toHaveBeenCalled();
  });

  it("says so in the build log", () => {
    const { logs } = runSetup(frontendHost({ extensions: [TIME] }));
    expect(logs[0]).not.toContain("Layout-wrapped");
  });
});

describe("route injection WITH a layout", () => {
  it("injects a GENERATED wrapper, not the extension page", () => {
    // This is the fix for "the admin panel has no formatting": the raw page
    // has no <head>, so it must not be the injected entrypoint.
    const { injected } = runSetup(frontendHost({ extensions: [TIME], layout: LAYOUT }));
    expect(injected).toHaveLength(1);
    expect(injected[0]!.pattern).toBe("/time");
    expect(injected[0]!.entrypoint).not.toBe("ext-time/pages/Index.astro");
    expect(injected[0]!.entrypoint).toContain("time.astro");
  });

  it("creates the wrapper directory under the PRODUCT root", () => {
    runSetup(frontendHost({ extensions: [TIME], layout: LAYOUT }));
    expect(mkdirSync).toHaveBeenCalledTimes(1);
    const [dir, opts] = vi.mocked(mkdirSync).mock.calls[0]!;
    expect(String(dir)).toContain("node_modules/.tds-frontend/routes/");
    expect(String(dir)).toContain("product");
    expect(opts).toEqual({ recursive: true });
  });

  it("wraps the page in the Layout with a real title", () => {
    runSetup(frontendHost({ extensions: [TIME], layout: LAYOUT }));
    const [, source] = vi.mocked(writeFileSync).mock.calls[0]!;
    expect(source).toContain(`import Layout from ${JSON.stringify(LAYOUT)};`);
    expect(source).toContain(`import Page from "ext-time/pages/Index.astro";`);
    expect(source).toContain(`<Layout title={"Zeiterfassung"}>`);
    // NESTED, not merely present: a `<Layout></Layout>` followed by `<Page />`
    // contains all three strings and still renders the page OUTSIDE the panel
    // chrome — which is the whole bug this wrapper exists to prevent.
    const src = String(source);
    expect(src.indexOf("<Page />")).toBeGreaterThan(src.indexOf("<Layout title"));
    expect(src.indexOf("</Layout>")).toBeGreaterThan(src.indexOf("<Page />"));
  });

  it("takes the title from the route's own NAV entry", () => {
    const two = defineExtension(
      base({
        id: "two",
        nav: [
          { id: "alpha", label: "Alpha", href: "/alpha" },
          { id: "beta", label: "Beta", href: "/beta" },
        ],
        routes: [
          { pattern: "/alpha", entrypoint: "x/Alpha.astro" },
          { pattern: "/beta", entrypoint: "x/Beta.astro" },
        ],
      }),
    );
    runSetup(frontendHost({ extensions: [two], layout: LAYOUT }));
    const sources = vi.mocked(writeFileSync).mock.calls.map(([, s]) => String(s));
    const alpha = sources.find((s) => s.includes("Alpha.astro"))!;
    const beta = sources.find((s) => s.includes("Beta.astro"))!;
    expect(alpha).toContain(`title={"Alpha"}`);
    expect(beta).toContain(`title={"Beta"}`);
  });

  it("falls back to a generic title for a route with no nav entry", () => {
    const hidden = defineExtension(
      base({ id: "hidden", routes: [{ pattern: "/hidden", entrypoint: "x/H.astro" }] }),
    );
    runSetup(frontendHost({ extensions: [hidden], layout: LAYOUT }));
    const [, source] = vi.mocked(writeFileSync).mock.calls[0]!;
    expect(source).toContain(`title={"Panel"}`);
  });

  it("escapes the specifiers so a quote cannot break the wrapper", () => {
    // The specifiers come from a manifest; JSON.stringify keeps generated
    // source valid rather than producing a syntax error at build time.
    runSetup(frontendHost({ extensions: [TIME], layout: 'weird"layout' }));
    const [, source] = vi.mocked(writeFileSync).mock.calls[0]!;
    expect(source).toContain('import Layout from "weird\\"layout";');
  });

  it("derives a filesystem-safe slug from the route pattern", () => {
    const nested = defineExtension(
      base({ id: "nested", routes: [{ pattern: "/blog/posts", entrypoint: "x/P.astro" }] }),
    );
    runSetup(frontendHost({ extensions: [nested], layout: LAYOUT }));
    const [file] = vi.mocked(writeFileSync).mock.calls[0]!;
    expect(String(file)).toContain("blog_posts.astro");
    expect(String(file)).not.toContain("blog/posts.astro");
  });

  it("gives distinct files to routes that differ only in separators", () => {
    const two = defineExtension(
      base({
        id: "two",
        routes: [
          { pattern: "/a/b", entrypoint: "x/AB.astro" },
          { pattern: "/a-c", entrypoint: "x/AC.astro" },
        ],
      }),
    );
    runSetup(frontendHost({ extensions: [two], layout: LAYOUT }));
    const files = vi.mocked(writeFileSync).mock.calls.map(([f]) => String(f));
    expect(new Set(files).size).toBe(2);
  });

  it("names the root route index rather than an empty file", () => {
    const root = defineExtension(
      base({ id: "root", routes: [{ pattern: "/", entrypoint: "x/Root.astro" }] }),
    );
    runSetup(frontendHost({ extensions: [root], layout: LAYOUT }));
    const [file] = vi.mocked(writeFileSync).mock.calls[0]!;
    expect(String(file)).toContain("index.astro");
  });

  it("writes one wrapper per route", () => {
    const many = defineExtension(
      base({
        id: "many",
        routes: [
          { pattern: "/one", entrypoint: "x/1.astro" },
          { pattern: "/two", entrypoint: "x/2.astro" },
          { pattern: "/three", entrypoint: "x/3.astro" },
        ],
      }),
    );
    const { injected } = runSetup(frontendHost({ extensions: [many], layout: LAYOUT }));
    expect(writeFileSync).toHaveBeenCalledTimes(3);
    expect(injected.map((r) => r.pattern)).toEqual(["/one", "/two", "/three"]);
  });

  it("says so in the build log", () => {
    const { logs } = runSetup(frontendHost({ extensions: [TIME], layout: LAYOUT }));
    expect(logs[0]).toContain("Layout-wrapped");
  });
});

describe("the build log", () => {
  it("names the composed extensions, routes, widgets and settings", () => {
    const { logs } = runSetup(frontendHost({ extensions: [TIME] }));
    expect(logs[0]).toContain("1 extension(s) [time-tracker]");
    expect(logs[0]).toContain("1 route(s)");
    expect(logs[0]).toContain("1 widget(s)");
    expect(logs[0]).toContain("1 settings panel(s)");
  });

  it("reports an empty build honestly", () => {
    const { logs } = runSetup(frontendHost({ extensions: [] }));
    expect(logs[0]).toContain("0 extension(s)");
  });
});

describe("the virtual modules", () => {
  const setup = (opts?: { layout?: string }) => {
    const { configs } = runSetup(frontendHost({ extensions: [TIME], ...opts }));
    return plugin(configs);
  };

  it("is registered as a Vite plugin", () => {
    const p = setup();
    expect(p.name).toBe("frontend-registry");
  });

  it("resolves the three virtual ids to internal module names", () => {
    const p = setup();
    for (const id of ["virtual:frontend-registry", "virtual:frontend-widgets", "virtual:frontend-settings"]) {
      expect(p.resolveId(id), id).toBe(`\0${id}`);
    }
  });

  it("ignores an id it does not own", () => {
    // Returning a value here would hijack an unrelated import.
    const p = setup();
    expect(p.resolveId("virtual:something-else")).toBeUndefined();
    expect(p.resolveId("react")).toBeUndefined();
    expect(p.load("\0virtual:something-else")).toBeUndefined();
  });

  it("serves the composed registry as data", () => {
    const p = setup();
    const code = p.load("\0virtual:frontend-registry")!;
    expect(code).toContain("export const registry =");
    const json = JSON.parse(code.slice(code.indexOf("{"), code.lastIndexOf("}") + 1));
    expect(json.order).toEqual(["time-tracker"]);
    expect(json.nav[0].href).toBe("/time");
    expect(json.routes[0].pattern).toBe("/time");
  });

  it("serves widgets with a STATICALLY IMPORTED component", () => {
    // Astro cannot hydrate a component named by a runtime string, so the
    // generated module must contain a real import statement.
    const p = setup();
    const code = p.load("\0virtual:frontend-widgets")!;
    expect(code).toContain(`import __C0 from "ext-time/widgets/Week.astro";`);
    expect(code).toContain("export const widgets = [");
    expect(code).toContain("Component: __C0");
  });

  it("keeps each widget's metadata alongside its component", () => {
    const p = setup();
    const code = p.load("\0virtual:frontend-widgets")!;
    expect(code).toContain('"id":"time-week"');
    expect(code).toContain('"title":"Diese Woche"');
  });

  it("serves settings panels the same way", () => {
    const p = setup();
    const code = p.load("\0virtual:frontend-settings")!;
    expect(code).toContain(`import __C0 from "ext-time/islands/Settings.astro";`);
    expect(code).toContain("export const settings = [");
  });

  it("gives each item its own import binding", () => {
    const many = defineExtension(
      base({
        id: "many",
        widgets: [
          { id: "w1", title: "One", island: "x/One.astro" },
          { id: "w2", title: "Two", island: "x/Two.astro" },
        ],
      }),
    );
    const { configs } = runSetup(frontendHost({ extensions: [many] }));
    const code = plugin(configs).load("\0virtual:frontend-widgets")!;
    expect(code).toContain(`import __C0 from "x/One.astro";`);
    expect(code).toContain(`import __C1 from "x/Two.astro";`);
    expect(code).toContain("Component: __C0");
    expect(code).toContain("Component: __C1");
  });

  it("emits a VALID empty module when a slot has no items", () => {
    // A malformed module here breaks the whole product build, not just the
    // dashboard — an extension set with no widgets is perfectly normal.
    const { configs } = runSetup(frontendHost({ extensions: [] }));
    const code = plugin(configs).load("\0virtual:frontend-widgets")!;
    expect(code).toContain("export const widgets = [");
    expect(code).not.toContain("import __C");
  });
});

/**
 * The virtual-module ids are PUBLIC names — a host writes them in an `import`.
 * They were renamed `virtual:panel-*` → `virtual:frontend-*` (the last `panel-`
 * names in the SDK). This package is stable at 1.x with additive minors only
 * (consumers pin `^1.0.0`), so the old spellings MUST keep resolving: dropping
 * them would break any host one version behind, at build time. Remove the
 * aliases only in a deliberate 2.0.0.
 */
describe("the deprecated virtual:panel-* aliases", () => {
  const LEGACY = ["virtual:panel-registry", "virtual:panel-widgets", "virtual:panel-settings"] as const;
  const CANONICAL = ["virtual:frontend-registry", "virtual:frontend-widgets", "virtual:frontend-settings"] as const;

  const setup = () => plugin(runSetup(frontendHost({ extensions: [TIME] })).configs);

  it("names the integration and its log prefix after the platform", () => {
    expect(frontendHost({ extensions: [] }).name).toBe("frontend-host");
    const { logs } = runSetup(frontendHost({ extensions: [TIME] }));
    expect(logs.join("\n")).toContain("frontend-host:");
    expect(logs.join("\n")).not.toContain("panel-host:");
  });

  it("still resolves every legacy spelling", () => {
    const p = setup();
    for (const id of LEGACY) expect(p.resolveId(id), id).toBeDefined();
  });

  it("maps each alias to the SAME internal id as its canonical name", () => {
    // Two internal ids would mean two module instances — two copies of the
    // registry in one build. That is the regression this pins down, and it is
    // why the aliases share a resolution table rather than each getting a \0id.
    const p = setup();
    LEGACY.forEach((legacy, i) => {
      expect(p.resolveId(legacy), legacy).toBe(p.resolveId(CANONICAL[i]!));
    });
  });

  it("serves identical content through an alias and its canonical id", () => {
    const p = setup();
    LEGACY.forEach((legacy, i) => {
      expect(p.load(p.resolveId(legacy)!), legacy).toBe(p.load(p.resolveId(CANONICAL[i]!)!));
    });
  });

  it("does not load a bare, unresolved id", () => {
    // Vite passes the resolved (\0-prefixed) form; anything else is not ours.
    const p = setup();
    expect(p.load("virtual:frontend-registry")).toBeUndefined();
    expect(p.load("virtual:panel-registry")).toBeUndefined();
  });
});
