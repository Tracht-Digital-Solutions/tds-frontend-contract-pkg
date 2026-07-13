import { describe, expect, it } from "vitest";

import { composeExtensions, defineExtension, validateManifest } from "../registry.js";
import type { ExtensionManifest } from "../types.js";

const base = (over: Partial<ExtensionManifest> & Pick<ExtensionManifest, "id">): ExtensionManifest => ({
  name: over.name ?? over.id,
  version: over.version ?? "0.1.0",
  ...over,
});

describe("validateManifest", () => {
  it("accepts a minimal valid manifest", () => {
    expect(validateManifest(base({ id: "time-tracker" }))).toEqual([]);
  });

  it("rejects a non-kebab id", () => {
    expect(validateManifest(base({ id: "TimeTracker" }))).toContain(
      'id must be kebab-case (got "TimeTracker")',
    );
  });

  it("flags duplicate contribution ids within one extension", () => {
    const errors = validateManifest(
      base({
        id: "blog-cms",
        widgets: [
          { id: "recent", title: "A", island: "x" },
          { id: "recent", title: "B", island: "y" },
        ],
      }),
    );
    expect(errors).toContain('duplicate widget id "recent"');
  });

  it("requires route patterns to be absolute with an entrypoint", () => {
    const errors = validateManifest(
      base({ id: "blog-cms", routes: [{ pattern: "blog", entrypoint: "" }] }),
    );
    expect(errors).toContain('route pattern must start with "/" (got "blog")');
    expect(errors).toContain('route "blog" is missing an entrypoint');
  });
});

describe("defineExtension", () => {
  it("throws with a readable message on an invalid manifest", () => {
    expect(() => defineExtension(base({ id: "Bad Id" }))).toThrow(/Invalid extension manifest/);
  });
});

describe("composeExtensions", () => {
  it("orders extensions by dependsOn and flattens contributions", () => {
    const timeTracker = defineExtension(
      base({
        id: "time-tracker",
        permissions: [{ id: "time:read", label: "Zeiten ansehen" }],
        nav: [{ id: "time", label: "Zeiterfassung", href: "/time", order: 20 }],
        widgets: [{ id: "time-week", title: "Diese Woche", island: "ext-time/Week", order: 10 }],
        routes: [{ pattern: "/time", entrypoint: "ext-time/pages/Index.astro" }],
        i18n: { de: { "time.title": "Zeiterfassung" }, en: { "time.title": "Time tracking" } },
      }),
    );
    const reports = defineExtension(
      base({
        id: "time-reports",
        dependsOn: ["time-tracker"],
        nav: [{ id: "time-reports", label: "Berichte", href: "/time/reports", order: 10 }],
      }),
    );

    // Pass out of dependency order to prove the sort.
    const registry = composeExtensions([reports, timeTracker]);

    expect(registry.order).toEqual(["time-tracker", "time-reports"]);
    expect(registry.permissions.map((p) => p.id)).toEqual(["time:read"]);
    // Nav sorted by order across extensions (reports.order 10 < time.order 20).
    expect(registry.nav.map((n) => n.id)).toEqual(["time-reports", "time"]);
    expect(registry.i18n.de["time.title"]).toBe("Zeiterfassung");
  });

  it("throws on a route pattern collision across extensions", () => {
    const a = defineExtension(base({ id: "a", routes: [{ pattern: "/x", entrypoint: "a/x" }] }));
    const b = defineExtension(base({ id: "b", routes: [{ pattern: "/x", entrypoint: "b/x" }] }));
    expect(() => composeExtensions([a, b])).toThrow(/Conflicting route id "\/x"/);
  });

  it("throws when a dependency is not enabled", () => {
    const reports = defineExtension(base({ id: "time-reports", dependsOn: ["time-tracker"] }));
    expect(() => composeExtensions([reports])).toThrow(/depends on "time-tracker"/);
  });

  it("throws on a dependency cycle", () => {
    const a = defineExtension(base({ id: "a", dependsOn: ["b"] }));
    const b = defineExtension(base({ id: "b", dependsOn: ["a"] }));
    expect(() => composeExtensions([a, b])).toThrow(/Dependency cycle/);
  });
});
