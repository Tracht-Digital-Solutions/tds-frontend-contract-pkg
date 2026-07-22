/**
 * `@tracht-digital-solutions/tds-frontend-contract` — the SDK every base panel and
 * extension builds against. Pure types + build-time composition helpers; the
 * Astro host glue lives in the `./astro` subexport, the PHP backend `Module`
 * contract in `php/src/*` (Composer package `tracht-digital-solutions/tds-frontend-contract`).
 */

export type {
  ComposedRegistry,
  ExtensionManifest,
  I18nStrings,
  NavEntry,
  PermissionDef,
  RouteDef,
  SettingsPanel,
  WidgetManifest,
  WidgetSize,
} from "./types.js";

export { composeExtensions, defineExtension, validateManifest } from "./registry.js";
