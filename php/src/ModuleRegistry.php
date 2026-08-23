<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

use Slim\App;
use Slim\Interfaces\RouteCollectorInterface;

/**
 * Composes a set of {@see Module}s for one base-API build — the PHP twin of the
 * TypeScript `composeExtensions`.
 *
 * Resolves dependency (load) order, rejects duplicate module ids / missing
 * dependencies / cycles, then lets the base:
 *   - mount every module's routes in order ({@see registerAll()}), recording
 *     which module each route came from ({@see routeOwners()}),
 *   - collect all Phinx migration dirs for the in-process auto-migrator
 *     ({@see migrationPaths()}),
 *   - gather the merged permission + settings catalog
 *     ({@see permissions()}, {@see settings()}).
 */
final class ModuleRegistry
{
    /** @var Module[] in resolved dependency order */
    private array $ordered;

    /** @var array<string, string> "<METHOD> <pattern>" => module id, filled by registerAll() */
    private array $routeOwners = [];

    /** @param Module[] $modules in any order */
    public function __construct(array $modules)
    {
        $byId = [];
        foreach ($modules as $module) {
            $id = $module->id();
            if (isset($byId[$id])) {
                throw new ModuleException("Duplicate module id \"{$id}\"");
            }
            $byId[$id] = $module;
        }
        $this->ordered = self::topoSort($byId);
    }

    /**
     * Mount every module's routes on the shared Slim app, in dependency order.
     *
     * Also records route ownership: the collector is read before and after each
     * `register()` call, and whatever appeared in between belongs to that
     * module. Nothing else can recover this — once composition is done, the
     * collector holds one flat list with no trace of who added what, which is
     * why the API reference used to group by first path segment and drop every
     * module's admin routes into one undifferentiated `admin` bucket.
     */
    public function registerAll(App $app): void
    {
        $collector = $app->getRouteCollector();
        $before = self::routeKeys($collector);
        foreach ($this->ordered as $module) {
            $module->register($app);
            $after = self::routeKeys($collector);
            foreach (array_diff($after, $before) as $key) {
                $this->routeOwners[$key] = $module->id();
            }
            $before = $after;
        }
    }

    /**
     * Which module mounted which route: `"<METHOD> <pattern>"` => module id.
     *
     * Empty until {@see registerAll()} has run, and never contains the base's
     * own routes — a route missing from this map belongs to the base kernel.
     *
     * @return array<string, string>
     */
    public function routeOwners(): array
    {
        return $this->routeOwners;
    }

    /** @return string[] "<METHOD> <pattern>" for every route currently mounted */
    private static function routeKeys(RouteCollectorInterface $collector): array
    {
        $keys = [];
        foreach ($collector->getRoutes() as $route) {
            foreach ($route->getMethods() as $method) {
                $keys[] = strtoupper($method) . ' ' . $route->getPattern();
            }
        }
        return $keys;
    }

    /**
     * All modules' Phinx migration directories, in dependency order. NB: the
     * migration CLASS names must be globally unique (see {@see Module::migrations()}).
     *
     * @return string[]
     */
    public function migrationPaths(): array
    {
        $paths = [];
        foreach ($this->ordered as $module) {
            foreach ($module->migrations() as $path) {
                $paths[] = $path;
            }
        }
        return $paths;
    }

    /** @return PermissionDef[] merged catalog, dependency-ordered */
    public function permissions(): array
    {
        $out = [];
        $seen = [];
        foreach ($this->ordered as $module) {
            foreach ($module->permissions() as $perm) {
                if (isset($seen[$perm->id])) {
                    throw new ModuleException(
                        "Conflicting permission id \"{$perm->id}\" (from module \"{$module->id()}\")",
                    );
                }
                $seen[$perm->id] = true;
                $out[] = $perm;
            }
        }
        return $out;
    }

    /** @return SettingDef[] merged catalog, dependency-ordered */
    public function settings(): array
    {
        $out = [];
        $seen = [];
        foreach ($this->ordered as $module) {
            foreach ($module->settings() as $setting) {
                if (isset($seen[$setting->key])) {
                    throw new ModuleException(
                        "Conflicting setting key \"{$setting->key}\" (from module \"{$module->id()}\")",
                    );
                }
                $seen[$setting->key] = true;
                $out[] = $setting;
            }
        }
        return $out;
    }

    /** Module ids in resolved dependency (load) order. @return string[] */
    public function order(): array
    {
        return array_map(static fn (Module $m): string => $m->id(), $this->ordered);
    }

    /**
     * The modules that contribute to the live notification feed.
     *
     * {@see NotificationSource} is an OPTIONAL capability — most modules do not
     * implement it, and that is not an error. Filtering here keeps the
     * `instanceof` in one place instead of in the base's feed route.
     *
     * @return NotificationSource[] dependency-ordered
     */
    public function notificationSources(): array
    {
        return array_values(array_filter(
            $this->ordered,
            static fn (Module $m): bool => $m instanceof NotificationSource,
        ));
    }

    /**
     * The modules that describe their routes for the admin API reference.
     *
     * Optional capability, same shape as {@see notificationSources()}: a module
     * without docs is not an error — its routes are still listed, just without
     * prose (see {@see ApiDocSource}).
     *
     * @return ApiDocSource[] dependency-ordered
     */
    public function apiDocSources(): array
    {
        return array_values(array_filter(
            $this->ordered,
            static fn (Module $m): bool => $m instanceof ApiDocSource,
        ));
    }

    /**
     * The path prefixes that count as public site reads, merged across modules.
     *
     * Optional capability ({@see SiteKeyProtected}); a module that declares
     * nothing is not an error. The result feeds the base's site-key middleware,
     * which compares a request path against these as prefixes.
     *
     * Two rules are enforced here rather than left to review, because both fail
     * silently: a prefix must be an absolute path (a relative one matches
     * nothing and looks exactly like a route somebody decided not to protect),
     * and no module may claim an `/admin` prefix — a site key is a machine
     * credential for reading public content, never a second way into the admin
     * surface.
     *
     * Duplicates are collapsed rather than rejected: two modules serving the
     * same public prefix is unusual but not wrong, and a hard error would make
     * the whole base unbootable over a cosmetic overlap.
     *
     * @return list<string> deduplicated, dependency-ordered
     */
    public function siteKeyRoutes(): array
    {
        $out = [];
        $seen = [];
        foreach ($this->ordered as $module) {
            if (!$module instanceof SiteKeyProtected) {
                continue;
            }
            foreach ($module->siteKeyRoutes() as $declared) {
                $prefix = rtrim(trim((string) $declared), '/');
                if ($prefix === '' || $prefix[0] !== '/') {
                    throw new ModuleException(
                        "Module \"{$module->id()}\" declared an invalid site-key prefix "
                        . "\"{$declared}\" (must be an absolute path)",
                    );
                }
                if ($prefix === '/admin' || str_starts_with($prefix, '/admin/')) {
                    throw new ModuleException(
                        "Module \"{$module->id()}\" declared \"{$prefix}\" as a site-key route; "
                        . 'admin routes are gated by UserContext, never by a site key',
                    );
                }
                if (isset($seen[$prefix])) {
                    continue;
                }
                $seen[$prefix] = true;
                $out[] = $prefix;
            }
        }
        return $out;
    }

    /**
     * Kahn-style topological sort by dependsOn.
     *
     * @param array<string, Module> $byId
     * @return Module[]
     */
    private static function topoSort(array $byId): array
    {
        $indegree = [];
        $dependents = [];
        foreach ($byId as $id => $module) {
            $indegree[$id] ??= 0;
            foreach ($module->dependsOn() as $dep) {
                if (!isset($byId[$dep])) {
                    throw new ModuleException(
                        "Module \"{$id}\" depends on \"{$dep}\", which is not enabled",
                    );
                }
                $indegree[$id]++;
                $dependents[$dep][] = $id;
            }
        }

        $queue = [];
        foreach ($byId as $id => $_module) {
            if ($indegree[$id] === 0) {
                $queue[] = $id;
            }
        }

        $result = [];
        while ($queue !== []) {
            $id = array_shift($queue);
            $result[] = $byId[$id];
            foreach ($dependents[$id] ?? [] as $dependent) {
                if (--$indegree[$dependent] === 0) {
                    $queue[] = $dependent;
                }
            }
        }

        if (count($result) !== count($byId)) {
            $resolved = array_map(static fn (Module $m): string => $m->id(), $result);
            $cyclic = array_values(array_diff(array_keys($byId), $resolved));
            throw new ModuleException('Dependency cycle among modules: ' . implode(', ', $cyclic));
        }
        return $result;
    }
}
