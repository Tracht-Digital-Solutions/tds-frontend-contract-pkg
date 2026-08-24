<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract\Tests;

use PHPUnit\Framework\TestCase;
use Tds\Frontend\Contract\CacheEvent;

/**
 * The wire shape of a cache event.
 *
 * Small surface, but the omissions are load-bearing: the site reads a MISSING
 * `id` as "every page of this type" and a MISSING `lang` as "both language
 * trees". Serialising them as `null` (or as `""`) would make the site look for
 * a post whose slug is the empty string and rebuild nothing at all — a save
 * that reports success and changes no page, which is the exact failure this
 * whole mechanism exists to avoid.
 */
final class CacheEventTest extends TestCase
{
    public function testFullEventCarriesEveryMember(): void
    {
        $event = new CacheEvent('post', 'mein-artikel', 'de');

        self::assertSame(
            ['type' => 'post', 'id' => 'mein-artikel', 'lang' => 'de'],
            $event->toArray(),
        );
    }

    public function testAbsentMembersAreOmittedRatherThanNull(): void
    {
        self::assertSame(['type' => 'catalog'], (new CacheEvent('catalog'))->toArray());
    }

    public function testEmptyStringsAreTreatedAsAbsent(): void
    {
        // A module reading an optional column gets '' rather than null often
        // enough that the two must mean the same thing here.
        self::assertSame(['type' => 'tool'], (new CacheEvent('tool', '', ''))->toArray());
    }

    public function testLanguageAgnosticEventKeepsItsId(): void
    {
        self::assertSame(
            ['type' => 'tool', 'id' => 'qr-code'],
            (new CacheEvent('tool', 'qr-code', null))->toArray(),
        );
    }
}
