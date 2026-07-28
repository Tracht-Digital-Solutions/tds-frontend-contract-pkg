import { describe, expect, it } from "vitest";

import { composeExtensions, defineExtension, validateManifest } from "../registry.js";
import type { ExtensionManifest } from "../types.js";

/**
 * The collision + ordering half of the contract.
 *
 * `registry.test.ts` covers the happy paths and one route collision. This file
 * covers the rest of the guard, because the guard is the whole point: a product
 * build folds every extension into ONE namespace with no prefixing, so a
 * duplicate id is a hard error — the frontend twin of the shared-`phinxlog`
 * "unique migration class" rule. If any contribution kind stops throwing, two
 * extensions silently overwrite each other in someone else's product build.
 */

const base = (over: Partial<ExtensionManifest> & Pick<ExtensionManifest, "id">): ExtensionManifest => ({
  name: over.name ?? over.id,
  version: over.version ?? "0.1.0",
  ...over,
});

describe("every contribution kind is collision-checked", () => {
  it("throws on a duplicate PERMISSION id across extensions", () => {
    const a = defineExtension(base({ id: "a", permissions: [{ id: "x:read", label: "A" }] }));
    const b = defineExtension(base({ id: "b", permissions: [{ id: "x:read", label: "B" }] }));
    expect(() => composeExtensions([a, b])).toThrow(/Conflicting permission id "x:read"/);
  });

  it("throws on a duplicate NAV id across extensions", () => {
    const a = defineExtension(base({ id: "a", nav: [{ id: "n", label: "A", href: "/a" }] }));
    const b = defineExtension(base({ id: "b", nav: [{ id: "n", label: "B", href: "/b" }] }));
    expect(() => composeExtensions([a, b])).toThrow(/Conflicting nav id "n"/);
  });

  it("throws on a duplicate WIDGET id across extensions", () => {
    const a = defineExtension(base({ id: "a", widgets: [{ id: "w", title: "A", island: "a/W" }] }));
    const b = defineExtension(base({ id: "b", widgets: [{ id: "w", title: "B", island: "b/W" }] }));
    expect(() => composeExtensions([a, b])).toThrow(/Conflicting widget id "w"/);
  });

  it("throws on a duplicate SETTINGS id across extensions", () => {
    const a = defineExtension(base({ id: "a", settings: [{ id: "s", label: "A", island: "a/S" }] }));
    const b = defineExtension(base({ id: "b", settings: [{ id: "s", label: "B", island: "b/S" }] }));
    expect(() => composeExtensions([a, b])).toThrow(/Conflicting settings id "s"/);
  });

  it("names the extension that caused the collision", () => {
    // The error is read by someone who did not write either extension.
    const a = defineExtension(base({ id: "alpha", nav: [{ id: "n", label: "A", href: "/a" }] }));
    const b = defineExtension(base({ id: "beta", nav: [{ id: "n", label: "B", href: "/b" }] }));
    expect(() => composeExtensions([a, b])).toThrow(/from extension "beta"/);
  });

  it("throws when the SAME extension is composed twice", () => {
    const a = defineExtension(base({ id: "a" }));
    expect(() => composeExtensions([a, a])).toThrow(/Duplicate extension id "a"/);
  });

  it("does not confuse ids across different KINDS", () => {
    // A nav entry and a widget may legitimately share an id — they live in
    // separate registries.
    const a = defineExtension(
      base({
        id: "a",
        nav: [{ id: "same", label: "N", href: "/a" }],
        widgets: [{ id: "same", title: "W", island: "a/W" }],
        settings: [{ id: "same", label: "S", island: "a/S" }],
        permissions: [{ id: "same", label: "P" }],
      }),
    );
    expect(() => composeExtensions([a])).not.toThrow();
  });
});

describe("ordering", () => {
  it("sorts nav, widgets and settings by order across extensions", () => {
    const a = defineExtension(
      base({
        id: "a",
        nav: [{ id: "a-late", label: "A", href: "/a", order: 90 }],
        widgets: [{ id: "wa", title: "A", island: "a/W", order: 90 }],
        settings: [{ id: "sa", label: "A", island: "a/S", order: 90 }],
      }),
    );
    const b = defineExtension(
      base({
        id: "b",
        nav: [{ id: "b-early", label: "B", href: "/b", order: 10 }],
        widgets: [{ id: "wb", title: "B", island: "b/W", order: 10 }],
        settings: [{ id: "sb", label: "B", island: "b/S", order: 10 }],
      }),
    );
    const r = composeExtensions([a, b]);
    expect(r.nav.map((n) => n.id)).toEqual(["b-early", "a-late"]);
    expect(r.widgets.map((w) => w.id)).toEqual(["wb", "wa"]);
    expect(r.settings.map((s) => s.id)).toEqual(["sb", "sa"]);
  });

  it("defaults a missing order to 100", () => {
    const early = defineExtension(base({ id: "early", nav: [{ id: "e", label: "E", href: "/e", order: 50 }] }));
    const none = defineExtension(base({ id: "none", nav: [{ id: "n", label: "N", href: "/n" }] }));
    const late = defineExtension(base({ id: "late", nav: [{ id: "l", label: "L", href: "/l", order: 150 }] }));
    expect(composeExtensions([late, none, early]).nav.map((n) => n.id)).toEqual(["e", "n", "l"]);
  });

  it("keeps composition order for equal `order` values (stable)", () => {
    // Without stability the sidebar would reshuffle between builds.
    const a = defineExtension(base({ id: "a", nav: [{ id: "a1", label: "A", href: "/a", order: 10 }] }));
    const b = defineExtension(base({ id: "b", nav: [{ id: "b1", label: "B", href: "/b", order: 10 }] }));
    const c = defineExtension(base({ id: "c", nav: [{ id: "c1", label: "C", href: "/c", order: 10 }] }));
    expect(composeExtensions([a, b, c]).nav.map((n) => n.id)).toEqual(["a1", "b1", "c1"]);
    expect(composeExtensions([c, b, a]).nav.map((n) => n.id)).toEqual(["c1", "b1", "a1"]);
  });

  it("leaves ROUTES in composition order, unsorted", () => {
    // Routes have no `order`; injecting them in a shuffled order would make
    // the build non-deterministic for no benefit.
    const a = defineExtension(base({ id: "a", routes: [{ pattern: "/z", entrypoint: "a/z" }] }));
    const b = defineExtension(base({ id: "b", routes: [{ pattern: "/a", entrypoint: "b/a" }] }));
    expect(composeExtensions([a, b]).routes.map((r) => r.pattern)).toEqual(["/z", "/a"]);
  });

  it("keeps independent extensions in the order they were passed", () => {
    const a = defineExtension(base({ id: "a" }));
    const b = defineExtension(base({ id: "b" }));
    const c = defineExtension(base({ id: "c" }));
    expect(composeExtensions([c, a, b]).order).toEqual(["c", "a", "b"]);
  });
});

describe("dependency resolution", () => {
  it("places a dependency before its dependent", () => {
    const dep = defineExtension(base({ id: "dep" }));
    const use = defineExtension(base({ id: "use", dependsOn: ["dep"] }));
    expect(composeExtensions([use, dep]).order).toEqual(["dep", "use"]);
  });

  it("resolves a chain", () => {
    const a = defineExtension(base({ id: "a" }));
    const b = defineExtension(base({ id: "b", dependsOn: ["a"] }));
    const c = defineExtension(base({ id: "c", dependsOn: ["b"] }));
    expect(composeExtensions([c, b, a]).order).toEqual(["a", "b", "c"]);
  });

  it("resolves a diamond", () => {
    const root = defineExtension(base({ id: "root" }));
    const left = defineExtension(base({ id: "left", dependsOn: ["root"] }));
    const right = defineExtension(base({ id: "right", dependsOn: ["root"] }));
    const tip = defineExtension(base({ id: "tip", dependsOn: ["left", "right"] }));
    const order = composeExtensions([tip, right, left, root]).order;
    expect(order[0]).toBe("root");
    expect(order.indexOf("tip")).toBe(3);
    expect(order.indexOf("left")).toBeLessThan(order.indexOf("tip"));
    expect(order.indexOf("right")).toBeLessThan(order.indexOf("tip"));
  });

  it("rejects an extension that depends on itself", () => {
    const a = defineExtension(base({ id: "a", dependsOn: ["a"] }));
    expect(() => composeExtensions([a])).toThrow(/Dependency cycle/);
  });

  it("names the extensions caught in a cycle", () => {
    const a = defineExtension(base({ id: "a", dependsOn: ["b"] }));
    const b = defineExtension(base({ id: "b", dependsOn: ["a"] }));
    const free = defineExtension(base({ id: "free" }));
    expect(() => composeExtensions([a, b, free])).toThrow(/a, b/);
  });
});

describe("i18n merging", () => {
  it("merges both languages across extensions", () => {
    const a = defineExtension(base({ id: "a", i18n: { de: { "a.x": "A" }, en: { "a.x": "A-en" } } }));
    const b = defineExtension(base({ id: "b", i18n: { de: { "b.y": "B" }, en: { "b.y": "B-en" } } }));
    const r = composeExtensions([a, b]);
    expect(r.i18n.de).toEqual({ "a.x": "A", "b.y": "B" });
    expect(r.i18n.en).toEqual({ "a.x": "A-en", "b.y": "B-en" });
  });

  it("lets the LATER extension win a key collision", () => {
    // Documented behaviour, and the reason extensions namespace their keys:
    // a silent overwrite is preferable to failing a whole product build over
    // a translation, but it must be predictable.
    const a = defineExtension(base({ id: "a", i18n: { de: { shared: "von A" }, en: {} } }));
    const b = defineExtension(base({ id: "b", i18n: { de: { shared: "von B" }, en: {} } }));
    expect(composeExtensions([a, b]).i18n.de.shared).toBe("von B");
    expect(composeExtensions([b, a]).i18n.de.shared).toBe("von A");
  });

  it("resolves a collision by DEPENDENCY order, not argument order", () => {
    const dep = defineExtension(base({ id: "dep", i18n: { de: { k: "dep" }, en: {} } }));
    const use = defineExtension(base({ id: "use", dependsOn: ["dep"], i18n: { de: { k: "use" }, en: {} } }));
    // Passed dependent-first; the dependency still loads first, so the
    // dependent's override wins either way.
    expect(composeExtensions([use, dep]).i18n.de.k).toBe("use");
    expect(composeExtensions([dep, use]).i18n.de.k).toBe("use");
  });

  it("always returns both language tables, even with no i18n at all", () => {
    // The shell indexes `i18n.de[key]`; a missing table would throw at render.
    const r = composeExtensions([defineExtension(base({ id: "a" }))]);
    expect(r.i18n.de).toEqual({});
    expect(r.i18n.en).toEqual({});
  });
});

describe("manifest validation", () => {
  it("requires a name and a version", () => {
    const errors = validateManifest({ id: "a" } as ExtensionManifest);
    expect(errors).toContain("name is required");
    expect(errors).toContain("version is required");
  });

  it("reports EVERY problem at once, not just the first", () => {
    // `validateManifest` exists separately from `defineExtension` for exactly
    // this: an author fixing one error at a time is a bad loop.
    const errors = validateManifest({ id: "Bad Id" } as ExtensionManifest);
    expect(errors.length).toBeGreaterThan(1);
  });

  it("accepts kebab ids with digits and multiple segments", () => {
    for (const id of ["a", "blog-cms", "tds-ext-2", "a1-b2-c3"]) {
      expect(validateManifest(base({ id })), id).toEqual([]);
    }
  });

  it("rejects ids that would break a nav key or route path", () => {
    for (const id of ["Blog", "blog_cms", "blog cms", "-blog", "blog-", "blog--cms", "1blog", ""]) {
      expect(validateManifest(base({ id })).join(" "), id).toContain("kebab-case");
    }
  });

  it("flags duplicate permission, nav and settings ids within one extension", () => {
    const errors = validateManifest(
      base({
        id: "a",
        permissions: [{ id: "p", label: "1" }, { id: "p", label: "2" }],
        nav: [{ id: "n", label: "1", href: "/1" }, { id: "n", label: "2", href: "/2" }],
        settings: [{ id: "s", label: "1", island: "x" }, { id: "s", label: "2", island: "y" }],
      }),
    );
    expect(errors).toContain('duplicate permission id "p"');
    expect(errors).toContain('duplicate nav id "n"');
    expect(errors).toContain('duplicate settings id "s"');
  });

  it("accepts an extension that contributes nothing at all", () => {
    // A backend-only extension is legitimate — it still needs a manifest.
    expect(validateManifest(base({ id: "backend-only" }))).toEqual([]);
  });

  it("throws from defineExtension naming the offending id", () => {
    // Anchored to the HEADER: the id also appears inside the validation
    // message below it, so a bare /"Bad"/ passes even when the header drops
    // it — and the header is what tells you which package failed to build.
    expect(() => defineExtension(base({ id: "Bad" }))).toThrow(/Invalid extension manifest "Bad"/);
  });

  it("returns the manifest unchanged when it is valid", () => {
    const manifest = base({ id: "a", nav: [{ id: "n", label: "N", href: "/n" }] });
    expect(defineExtension(manifest)).toBe(manifest);
  });
});
