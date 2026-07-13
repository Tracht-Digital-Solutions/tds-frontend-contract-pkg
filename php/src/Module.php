<?php
declare(strict_types=1);

namespace Tds\Panel\Contract;

use Slim\App;

/**
 * The backend half of the panel extension contract.
 *
 * A base API (`core-panel-api`) composes extensions IN PROCESS: it loads each
 * extension's `Module` and, in dependency order, calls {@see Module::register()}
 * to mount routes, collects its {@see Module::migrations()} for the in-process
 * auto-migrator, and merges its {@see Module::permissions()} + {@see
 * Module::settings()}. This mirrors the TypeScript `ExtensionManifest` on the
 * frontend and fits the existing gateway "one PHP-FPM app, no service processes"
 * model (see the four services the gateway already loads in-process).
 *
 * Implementations should extend {@see AbstractModule} for sane defaults.
 */
interface Module
{
    /** Stable, kebab-case id, e.g. "time-tracker". Unique across the product. */
    public function id(): string;

    /**
     * ids of other modules this one depends on / mounts into. The registry
     * registers dependencies first (topological order).
     *
     * @return string[]
     */
    public function dependsOn(): array;

    /**
     * Mount routes (and any module-local middleware) on the shared Slim app.
     * Called once, in dependency order, after the base has added its own
     * routing/CORS middleware.
     */
    public function register(App $app): void;

    /**
     * Absolute paths to this module's Phinx migration directories.
     *
     * IMPORTANT: migration CLASS names must be globally unique across every
     * module — the in-process auto-migrator `include`s them all into one PHP
     * process, so a reused class name is an uncatchable fatal redeclaration.
     * Prefix each migration with the module id (e.g. `CreateTimeTrackerEntry`).
     *
     * @return string[]
     */
    public function migrations(): array;

    /**
     * RBAC permissions this module contributes to the catalog.
     *
     * @return PermissionDef[]
     */
    public function permissions(): array;

    /**
     * Runtime `app_setting` keys this module reads (surfaced in the admin
     * Einstellungen / Einrichtungsassistent). Secrets are stored encrypted.
     *
     * @return SettingDef[]
     */
    public function settings(): array;
}
