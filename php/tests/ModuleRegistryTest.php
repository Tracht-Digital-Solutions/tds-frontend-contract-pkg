<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract\Tests;

use PHPUnit\Framework\TestCase;
use Slim\App;
use Tds\Frontend\Contract\AbstractModule;
use Tds\Frontend\Contract\ModuleException;
use Tds\Frontend\Contract\ModuleRegistry;
use Tds\Frontend\Contract\PermissionDef;

/** A test double module with configurable id/deps/permissions. */
final class FakeModule extends AbstractModule
{
    /** @var string[] $registered records the register() call order */
    public static array $registered = [];

    /**
     * @param string[]        $deps
     * @param PermissionDef[] $perms
     */
    public function __construct(
        private readonly string $id,
        private readonly array $deps = [],
        private readonly array $perms = [],
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    /** @return string[] */
    public function dependsOn(): array
    {
        return $this->deps;
    }

    public function register(App $app): void
    {
        self::$registered[] = $this->id;
    }

    /** @return PermissionDef[] */
    public function permissions(): array
    {
        return $this->perms;
    }
}

final class ModuleRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        FakeModule::$registered = [];
    }

    public function testResolvesDependencyOrder(): void
    {
        $registry = new ModuleRegistry([
            new FakeModule('time-reports', ['time-tracker']),
            new FakeModule('time-tracker'),
        ]);

        self::assertSame(['time-tracker', 'time-reports'], $registry->order());
    }

    public function testRegisterAllRunsInDependencyOrder(): void
    {
        $registry = new ModuleRegistry([
            new FakeModule('b', ['a']),
            new FakeModule('a'),
        ]);
        $registry->registerAll($this->createStub(App::class));

        self::assertSame(['a', 'b'], FakeModule::$registered);
    }

    public function testRejectsDuplicateModuleId(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('Duplicate module id "a"');
        new ModuleRegistry([new FakeModule('a'), new FakeModule('a')]);
    }

    public function testRejectsMissingDependency(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('depends on "missing"');
        new ModuleRegistry([new FakeModule('a', ['missing'])]);
    }

    public function testRejectsDependencyCycle(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('Dependency cycle');
        new ModuleRegistry([new FakeModule('a', ['b']), new FakeModule('b', ['a'])]);
    }

    public function testMergesPermissionsAndRejectsConflicts(): void
    {
        $registry = new ModuleRegistry([
            new FakeModule('a', [], [new PermissionDef('time:read', 'Zeiten ansehen')]),
        ]);
        self::assertCount(1, $registry->permissions());

        $conflicting = new ModuleRegistry([
            new FakeModule('a', [], [new PermissionDef('x:read', 'A')]),
            new FakeModule('b', [], [new PermissionDef('x:read', 'B')]),
        ]);
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('Conflicting permission id "x:read"');
        $conflicting->permissions();
    }
}
