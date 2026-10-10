<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract\Tests;

use PHPUnit\Framework\TestCase;
use Slim\App;
use Tds\Frontend\Contract\AbstractModule;
use Tds\Frontend\Contract\Commerce\ReferralMatch;
use Tds\Frontend\Contract\Commerce\ReferralResolver;
use Tds\Frontend\Contract\Commerce\SaleEvent;
use Tds\Frontend\Contract\Commerce\SaleEvents;
use Tds\Frontend\Contract\Commerce\SaleListener;
use Tds\Frontend\Contract\ModuleRegistry;

final class SaleEventsTest extends TestCase
{
    public function testAThrowingListenerReachesNeitherTheOthersNorTheSeller(): void
    {
        $seen = [];
        $failures = [];
        $events = new SaleEvents(
            [
                new RecordingListener('a', $seen, throw: true),
                new RecordingListener('b', $seen),
            ],
            [],
            static function (\Throwable $e) use (&$failures): void {
                $failures[] = $e->getMessage();
            },
        );

        $events->paid(new SaleEvent('shop', '7', 1000));
        $events->reversed('shop', '7');

        self::assertSame(['a:paid:shop:7', 'b:paid:shop:7', 'a:reversed:shop:7', 'b:reversed:shop:7'], $seen);
        self::assertSame(['a broke', 'a broke'], $failures);
        self::assertCount(1, $events->failures(), 'failures() describes the last dispatch only');
    }

    public function testEmptyDispatcherIsANoOp(): void
    {
        $events = new SaleEvents();
        $events->paid(new SaleEvent('billing', '1', 0));
        self::assertNull($events->resolveReferral('ANNA'));
        self::assertSame([], $events->failures());
    }

    public function testResolveSkipsBlankAndBrokenResolvers(): void
    {
        $events = new SaleEvents([], [new BrokenResolver(), new MapResolver(['ANNA' => 'Anna B.'])]);

        self::assertNull($events->resolveReferral('  '));
        self::assertNull($events->resolveReferral(null));
        self::assertNull($events->resolveReferral('NOBODY'));
        $match = $events->resolveReferral(' ANNA ');
        self::assertNotNull($match);
        self::assertSame('Anna B.', $match->displayName);
        self::assertCount(1, $events->failures());
    }

    public function testRegistryCollectsBothCapabilities(): void
    {
        $registry = new ModuleRegistry([
            new PlainModule('plain'),
            new CommerceModule('referrals', ['plain']),
        ]);
        self::assertCount(1, $registry->saleListeners());
        self::assertCount(1, $registry->referralResolvers());
        self::assertSame([], (new ModuleRegistry([new PlainModule('a')]))->saleListeners());
    }
}

final class RecordingListener implements SaleListener
{
    /** @param list<string> $seen */
    public function __construct(private readonly string $name, private array &$seen, private readonly bool $throw = false)
    {
    }

    public function onSalePaid(SaleEvent $sale): void
    {
        $this->seen[] = "{$this->name}:paid:{$sale->source}:{$sale->sourceId}";
        if ($this->throw) {
            throw new \RuntimeException("{$this->name} broke");
        }
    }

    public function onSaleReversed(string $source, string $sourceId): void
    {
        $this->seen[] = "{$this->name}:reversed:{$source}:{$sourceId}";
        if ($this->throw) {
            throw new \RuntimeException("{$this->name} broke");
        }
    }
}

final class BrokenResolver implements ReferralResolver
{
    public function resolveReferral(string $code): ?ReferralMatch
    {
        throw new \RuntimeException('db down');
    }
}

final class MapResolver implements ReferralResolver
{
    /** @param array<string,string> $map */
    public function __construct(private readonly array $map)
    {
    }

    public function resolveReferral(string $code): ?ReferralMatch
    {
        return isset($this->map[$code]) ? new ReferralMatch($code, $this->map[$code]) : null;
    }
}

final class CommerceModule extends AbstractModule implements SaleListener, ReferralResolver
{
    /** @param string[] $deps */
    public function __construct(private readonly string $id, private readonly array $deps = [])
    {
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

    public function onSalePaid(SaleEvent $sale): void
    {
    }

    public function onSaleReversed(string $source, string $sourceId): void
    {
    }

    public function resolveReferral(string $code): ?ReferralMatch
    {
        return null;
    }
}

final class PlainModule extends AbstractModule
{
    public function __construct(private readonly string $id)
    {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function register(App $app): void
    {
    }
}
