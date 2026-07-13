<?php
declare(strict_types=1);

namespace Tds\Panel\Contract;

/** Thrown by {@see ModuleRegistry} on a bad module set (duplicate id, missing dep, cycle). */
final class ModuleException extends \RuntimeException
{
}
