<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract\Tests;

use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Factory\AppFactory;
use Tds\Frontend\Contract\AbstractModule;
use Tds\Frontend\Contract\ApiDocSource;
use Tds\Frontend\Contract\ModuleException;
use Tds\Frontend\Contract\ModuleRegistry;
use Tds\Frontend\Contract\NotificationSource;
use Tds\Frontend\Contract\PermissionDef;
use Tds\Frontend\Contract\UserContext;

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

/** A module that also contributes to the live notification feed. */
final class FakeNotifyingModule extends AbstractModule implements NotificationSource
{
    /** @param string[] $deps */
    public function __construct(
        private readonly string $id,
        private readonly array $deps = [],
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
    }

    /** @return array{cursor: string, items: list<array<string,mixed>>} */
    public function notifications(UserContext $user, ?string $cursor): array
    {
        return ['cursor' => '1', 'items' => []];
    }
}

/** A module that mounts real routes and describes them for the API reference. */
final class FakeRoutingModule extends AbstractModule implements ApiDocSource
{
    /**
     * @param list<array{0: string, 1: string}>                 $routes  [method, pattern]
     * @param list<array<string, mixed>>                        $docs
     */
    public function __construct(
        private readonly string $id,
        private readonly array $routes = [],
        private readonly array $docs = [],
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function register(App $app): void
    {
        foreach ($this->routes as [$method, $pattern]) {
            $app->map([$method], $pattern, static fn ($req, $res) => $res);
        }
    }

    /** @return list<array<string, mixed>> */
    public function apiDocs(): array
    {
        return $this->docs;
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

    public function testCollectsNotificationSourcesInDependencyOrder(): void
    {
        $registry = new ModuleRegistry([
            new FakeNotifyingModule('late', ['plain']),
            new FakeModule('plain'),
            new FakeNotifyingModule('early'),
        ]);

        $ids = array_map(
            static fn (object $m): string => $m->id(), // @phpstan-ignore-line
            $registry->notificationSources(),
        );
        self::assertSame(['early', 'late'], $ids);
    }

    public function testAModuleWithoutTheCapabilityIsNotAnError(): void
    {
        // NotificationSource is optional — most modules never implement it, and
        // a registry of only such modules must simply contribute nothing.
        $registry = new ModuleRegistry([new FakeModule('a'), new FakeModule('b')]);
        self::assertSame([], $registry->notificationSources());
        self::assertSame([], $registry->apiDocSources());
    }

    public function testCollectsApiDocSourcesInDependencyOrder(): void
    {
        $registry = new ModuleRegistry([
            new FakeRoutingModule('late'),
            new FakeModule('plain'),
            new FakeRoutingModule('early'),
        ]);

        $ids = array_map(
            static fn (object $m): string => $m->id(), // @phpstan-ignore-line
            $registry->apiDocSources(),
        );
        // FakeRoutingModule declares no deps, so order is registration order.
        self::assertSame(['late', 'early'], $ids);
    }

    public function testRecordsWhichModuleMountedWhichRoute(): void
    {
        // The whole point: after composition the collector is one flat list. If
        // ownership is not captured here it cannot be recovered, and the API
        // reference has to guess from the path — which puts every module's
        // /admin/* routes in one bucket.
        $app = AppFactory::create();
        $app->get('/base-route', static fn ($req, $res) => $res);

        $registry = new ModuleRegistry([
            new FakeRoutingModule('tickets', [['GET', '/tickets'], ['POST', '/tickets']]),
            new FakeRoutingModule('blog-cms', [['GET', '/admin/blog-cms/posts']]),
        ]);
        $registry->registerAll($app);

        self::assertSame([
            'GET /tickets' => 'tickets',
            'POST /tickets' => 'tickets',
            'GET /admin/blog-cms/posts' => 'blog-cms',
        ], $registry->routeOwners());
    }

    public function testBaseRoutesHaveNoOwner(): void
    {
        // Routes the base mounted itself — before or after composition — must
        // not be attributed to whichever module happened to register next.
        $app = AppFactory::create();
        $app->get('/wiki.json', static fn ($req, $res) => $res);

        $registry = new ModuleRegistry([new FakeRoutingModule('a', [['GET', '/a']])]);
        $registry->registerAll($app);

        self::assertArrayNotHasKey('GET /wiki.json', $registry->routeOwners());
        self::assertSame(['GET /a' => 'a'], $registry->routeOwners());
    }

    public function testRouteOwnersIsEmptyBeforeRegistration(): void
    {
        $registry = new ModuleRegistry([new FakeRoutingModule('a', [['GET', '/a']])]);
        self::assertSame([], $registry->routeOwners());
    }
}
