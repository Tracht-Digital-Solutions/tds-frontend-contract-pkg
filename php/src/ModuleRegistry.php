<?php
declare(strict_types=1);

namespace Tds\Panel\Contract;

use Slim\App;

/**
 * Composes a set of {@see Module}s for one base-API build — the PHP twin of the
 * TypeScript `composeExtensions`.
 *
 * Resolves dependency (load) order, rejects duplicate module ids / missing
 * dependencies / cycles, then lets the base:
 *   - mount every module's routes in order ({@see registerAll()}),
 *   - collect all Phinx migration dirs for the in-process auto-migrator
 *     ({@see migrationPaths()}),
 *   - gather the merged permission + settings catalog
 *     ({@see permissions()}, {@see settings()}).
 */
final class ModuleRegistry
{
    /** @var Module[] in resolved dependency order */
    private array $ordered;

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

    /** Mount every module's routes on the shared Slim app, in dependency order. */
    public function registerAll(App $app): void
    {
        foreach ($this->ordered as $module) {
            $module->register($app);
        }
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
