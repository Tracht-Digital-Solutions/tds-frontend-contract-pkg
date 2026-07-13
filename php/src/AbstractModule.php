<?php
declare(strict_types=1);

namespace Tds\Panel\Contract;

/**
 * Convenience base: an extension only overrides what it actually contributes.
 * `id()` and `register()` stay abstract (every module has both); everything
 * else defaults to "contributes nothing".
 */
abstract class AbstractModule implements Module
{
    /** @return string[] */
    public function dependsOn(): array
    {
        return [];
    }

    /** @return string[] */
    public function migrations(): array
    {
        return [];
    }

    /** @return PermissionDef[] */
    public function permissions(): array
    {
        return [];
    }

    /** @return SettingDef[] */
    public function settings(): array
    {
        return [];
    }
}
