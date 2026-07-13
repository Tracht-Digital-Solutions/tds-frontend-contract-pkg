/**
 * Composition helpers for the panel extension contract.
 *
 * These run at BUILD time in the host (`core-panel-frontend`): the product's
 * config imports each extension's {@link ExtensionManifest}, and
 * {@link composeExtensions} resolves dependency order, catches conflicts, and
 * flattens the contributions into one {@link ComposedRegistry} the shell +
 * Astro integration consume. Pure + dependency-free so it is trivially unit
 * tested (see `__tests__/registry.test.ts`).
 */

import type {
  ComposedRegistry,
  ExtensionManifest,
  I18nStrings,
  NavEntry,
  PermissionDef,
  RouteDef,
  SettingsPanel,
  WidgetManifest,
} from "./types.js";

const KEBAB = /^[a-z][a-z0-9]*(-[a-z0-9]+)*$/;

/**
 * Identity helper an extension's entry uses to export its manifest, e.g.
 * `export default defineExtension({ id: "time-tracker", ... })`. Validates
 * eagerly so a malformed manifest fails at the extension's own build/test,
 * not deep inside a product build. Throws on the first error.
 */
export function defineExtension(manifest: ExtensionManifest): ExtensionManifest {
  const errors = validateManifest(manifest);
  if (errors.length > 0) {
    throw new Error(
      `Invalid extension manifest "${manifest.id ?? "<no id>"}":\n  - ${errors.join("\n  - ")}`,
    );
  }
  return manifest;
}

/**
 * Structural validation of a single manifest. Returns a list of human-readable
 * problems (empty = valid). Kept separate from {@link defineExtension} so tests
 * and tooling can report all issues at once.
 */
export function validateManifest(manifest: ExtensionManifest): string[] {
  const errors: string[] = [];
  if (!manifest.id || !KEBAB.test(manifest.id)) {
    errors.push(`id must be kebab-case (got ${JSON.stringify(manifest.id)})`);
  }
  if (!manifest.name) errors.push("name is required");
  if (!manifest.version) errors.push("version is required");

  const dupWithin = (items: { id: string }[] | undefined, kind: string) => {
    if (!items) return;
    const seen = new Set<string>();
    for (const item of items) {
      if (seen.has(item.id)) errors.push(`duplicate ${kind} id "${item.id}"`);
      seen.add(item.id);
    }
  };
  dupWithin(manifest.permissions, "permission");
  dupWithin(manifest.nav, "nav");
  dupWithin(manifest.widgets, "widget");
  dupWithin(manifest.settings, "settings");

  for (const route of manifest.routes ?? []) {
    if (!route.pattern?.startsWith("/")) {
      errors.push(`route pattern must start with "/" (got ${JSON.stringify(route.pattern)})`);
    }
    if (!route.entrypoint) errors.push(`route "${route.pattern}" is missing an entrypoint`);
  }
  return errors;
}

/**
 * Compose a set of extensions into one flattened registry for a product build.
 *
 * - Resolves load order by `dependsOn` (topological); throws on a missing
 *   dependency or a dependency cycle.
 * - Throws on a duplicate id **across** extensions for any contribution kind
 *   (permission / nav / widget / settings / route pattern) — the base's
 *   in-one-build model has no namespacing, so collisions are hard errors, the
 *   frontend twin of the Phinx "unique migration class name" rule.
 * - Merges i18n; on a key collision the later (dependency-wise) extension wins.
 * - Sorts nav / widgets / settings by `order` (default 100), stable within ties.
 *
 * @param manifests the enabled extensions, in any order.
 */
export function composeExtensions(manifests: ExtensionManifest[]): ComposedRegistry {
  const byId = new Map<string, ExtensionManifest>();
  for (const m of manifests) {
    if (byId.has(m.id)) throw new Error(`Duplicate extension id "${m.id}"`);
    byId.set(m.id, m);
  }

  const order = topoSort(manifests, byId);
  const ordered = order.map((id) => byId.get(id)!);

  const permissions: PermissionDef[] = [];
  const nav: NavEntry[] = [];
  const widgets: WidgetManifest[] = [];
  const settings: SettingsPanel[] = [];
  const routes: RouteDef[] = [];
  const i18n: I18nStrings = { de: {}, en: {} };

  const claim = (registry: Set<string>, id: string, kind: string, ext: string) => {
    if (registry.has(id)) {
      throw new Error(`Conflicting ${kind} id "${id}" (from extension "${ext}")`);
    }
    registry.add(id);
  };
  const permIds = new Set<string>();
  const navIds = new Set<string>();
  const widgetIds = new Set<string>();
  const settingsIds = new Set<string>();
  const routePatterns = new Set<string>();

  for (const ext of ordered) {
    for (const p of ext.permissions ?? []) {
      claim(permIds, p.id, "permission", ext.id);
      permissions.push(p);
    }
    for (const n of ext.nav ?? []) {
      claim(navIds, n.id, "nav", ext.id);
      nav.push(n);
    }
    for (const w of ext.widgets ?? []) {
      claim(widgetIds, w.id, "widget", ext.id);
      widgets.push(w);
    }
    for (const s of ext.settings ?? []) {
      claim(settingsIds, s.id, "settings", ext.id);
      settings.push(s);
    }
    for (const r of ext.routes ?? []) {
      claim(routePatterns, r.pattern, "route", ext.id);
      routes.push(r);
    }
    if (ext.i18n) {
      Object.assign(i18n.de, ext.i18n.de);
      Object.assign(i18n.en, ext.i18n.en);
    }
  }

  const byOrder = <T extends { order?: number }>(a: T, b: T) =>
    (a.order ?? 100) - (b.order ?? 100);

  return {
    order,
    permissions,
    nav: stableSort(nav, byOrder),
    widgets: stableSort(widgets, byOrder),
    settings: stableSort(settings, byOrder),
    routes,
    i18n,
  };
}

/** Kahn-style topological sort by `dependsOn`; throws on missing dep / cycle. */
function topoSort(
  manifests: ExtensionManifest[],
  byId: Map<string, ExtensionManifest>,
): string[] {
  const indegree = new Map<string, number>();
  const dependents = new Map<string, string[]>();
  for (const m of manifests) {
    indegree.set(m.id, indegree.get(m.id) ?? 0);
    for (const dep of m.dependsOn ?? []) {
      if (!byId.has(dep)) {
        throw new Error(`Extension "${m.id}" depends on "${dep}", which is not enabled`);
      }
      indegree.set(m.id, (indegree.get(m.id) ?? 0) + 1);
      dependents.set(dep, [...(dependents.get(dep) ?? []), m.id]);
    }
  }

  // Seed with dependency-free extensions in declaration order for determinism.
  const queue = manifests.filter((m) => (indegree.get(m.id) ?? 0) === 0).map((m) => m.id);
  const result: string[] = [];
  while (queue.length > 0) {
    const id = queue.shift()!;
    result.push(id);
    for (const dependent of dependents.get(id) ?? []) {
      const next = (indegree.get(dependent) ?? 0) - 1;
      indegree.set(dependent, next);
      if (next === 0) queue.push(dependent);
    }
  }

  if (result.length !== manifests.length) {
    const cyclic = manifests.map((m) => m.id).filter((id) => !result.includes(id));
    throw new Error(`Dependency cycle among extensions: ${cyclic.join(", ")}`);
  }
  return result;
}

/** Stable sort (Array.prototype.sort is spec-stable, but be explicit + typed). */
function stableSort<T>(items: T[], cmp: (a: T, b: T) => number): T[] {
  return items
    .map((value, index) => ({ value, index }))
    .sort((a, b) => cmp(a.value, b.value) || a.index - b.index)
    .map((entry) => entry.value);
}
