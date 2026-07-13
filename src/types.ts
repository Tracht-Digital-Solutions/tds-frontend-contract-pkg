/**
 * The panel extension contract (frontend side).
 *
 * `panel-contract` is the single source of truth for how a **base panel**
 * (`core-panel-frontend`) discovers and composes **extensions** at BUILD time.
 * There is no runtime plugin loading — the host imports each extension's
 * {@link ExtensionManifest} and folds it into one static build (`output:
 * "static"`, no Node on prod). The PHP half of this package (`php/src/*`)
 * mirrors these shapes for the backend `Module` contract.
 *
 * Everything user-facing here (labels) is German — it is editable copy and, per
 * the TDS convention, lives with the contract/extension, not inlined in a page.
 */

/**
 * A fine-grained capability an extension introduces into the RBAC catalog.
 * Mirrors the PHP `PermissionDef`. The base merges every extension's
 * permissions into one catalog surfaced in the user editor; admins bypass all
 * checks (admin access is a boolean on the user, never a permission id).
 */
export interface PermissionDef {
  /** Stable id, `resource:action`, e.g. `"time:read"`. Globally unique. */
  id: string;
  /** German label shown in the admin user editor. */
  label: string;
  /** Optional grouping key for the RBAC UI (e.g. `"time-tracker"`). */
  group?: string;
}

/** Grid footprint hint for a dashboard widget. */
export type WidgetSize = "sm" | "md" | "lg";

/**
 * A left-navigation entry an extension contributes to the base shell.
 * The shell renders entries the current principal is permitted to see,
 * grouped by {@link NavEntry.group} and sorted by {@link NavEntry.order}.
 */
export interface NavEntry {
  /** Stable id, unique across all extensions. */
  id: string;
  /** German label. */
  label: string;
  /** Route path this links to, e.g. `"/time"`. Must match an injected route. */
  href: string;
  /** Icon key resolved by the shell's icon set. */
  icon?: string;
  /** Nav section id. An extension may target another extension's group id. */
  group?: string;
  /** Sort order within the group. Default 100. */
  order?: number;
  /** Permission id required to see the entry. Admins bypass. */
  permission?: string;
}

/**
 * A dashboard widget an extension contributes. The base Dashboard is a HOST:
 * it renders the enabled + permitted widgets and persists each user's chosen
 * layout (the "user-based dashboard"). The widget body is a React island the
 * host mounts; its data comes from {@link WidgetManifest.dataEndpoint}.
 */
export interface WidgetManifest {
  /** Stable id, unique across all extensions. */
  id: string;
  /** German card title. */
  title: string;
  /** Import specifier of the React island rendering the widget body. */
  island: string;
  /** Grid footprint. Default `"md"`. */
  size?: WidgetSize;
  /** Permission id required to render the widget. Admins bypass. */
  permission?: string;
  /** API path (relative to the panel's API base) the island fetches. */
  dataEndpoint?: string;
  /** Default sort order in the widget picker / grid. Default 100. */
  order?: number;
}

/**
 * A settings section an extension contributes to the base's Einstellungen
 * (`/einstellungen`) and, optionally, the Einrichtungsassistent (`/setup`).
 */
export interface SettingsPanel {
  /** Stable id, unique across all extensions. */
  id: string;
  /** German section label. */
  label: string;
  /** Import specifier of the React island rendering the settings form. */
  island: string;
  /** Permission id required to open the section. Admins bypass. */
  permission?: string;
  /** Also surface as a step in the Einrichtungsassistent. Default false. */
  inSetupWizard?: boolean;
  /** Sort order. Default 100. */
  order?: number;
}

/**
 * A page route an extension injects into the panel build. The host's Astro
 * integration turns each of these into an `injectRoute()` call at build time.
 */
export interface RouteDef {
  /** URL pattern, e.g. `"/time"` or `"/blog/[id]"`. Unique across extensions. */
  pattern: string;
  /** Astro page entrypoint (import specifier), e.g. `"ext-time/pages/Index.astro"`. */
  entrypoint: string;
  /** Permission id the page guards on (documented here; enforced by the page). */
  permission?: string;
}

/** Per-locale string tables an extension contributes to the shared i18n dict. */
export interface I18nStrings {
  de: Record<string, string>;
  en: Record<string, string>;
}

/**
 * The complete contribution surface of one extension (frontend side).
 * An extension package's entry exports one of these (via {@link defineExtension}).
 */
export interface ExtensionManifest {
  /** Stable, kebab-case id, e.g. `"time-tracker"`. Unique across the product. */
  id: string;
  /** German display name for the extension registry UI. */
  name: string;
  /** Semver of the extension package. */
  version: string;
  /** ids of other extensions this one depends on / mounts into. */
  dependsOn?: string[];
  permissions?: PermissionDef[];
  nav?: NavEntry[];
  widgets?: WidgetManifest[];
  settings?: SettingsPanel[];
  routes?: RouteDef[];
  i18n?: I18nStrings;
}

/**
 * The flattened result of composing a set of extensions for one product build.
 * Produced by `composeExtensions` and consumed by the host shell + Astro
 * integration. Arrays are dependency-ordered then sorted by `order`.
 */
export interface ComposedRegistry {
  /** Extension ids in resolved dependency (load) order. */
  order: string[];
  permissions: PermissionDef[];
  nav: NavEntry[];
  widgets: WidgetManifest[];
  settings: SettingsPanel[];
  routes: RouteDef[];
  /** Merged i18n across all extensions (later ids win on key collision). */
  i18n: I18nStrings;
}
