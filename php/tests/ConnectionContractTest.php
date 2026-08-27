<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract\Tests;

use PHPUnit\Framework\TestCase;
use Tds\Frontend\Contract\CacheResult;
use Tds\Frontend\Contract\SiteConnectionIdentity;
use Tds\Frontend\Contract\SitePairing;

final class ConnectionContractTest extends TestCase
{
    public function testCacheReportIsTruthful(): void
    {
        self::assertTrue((new CacheResult(CacheResult::REFRESHED, ['/']))->toArray()['cached']);
        self::assertFalse((new CacheResult(CacheResult::SKIPPED, skipped: ['/private']))->toArray()['cached']);
        self::assertFalse((new CacheResult(CacheResult::FAILED, failed: [['status' => 500]]))->toArray()['cached']);
    }

    public function testRequestIdentityMatchesScopesOnSegmentBoundaries(): void
    {
        $identity = new SiteConnectionIdentity(4, 'blog', 'blog', 'journal', ['blog_id' => 7], ['/content/blog']);
        self::assertTrue($identity->isConnected());
        self::assertTrue($identity->allows('/content/blog/post'));
        self::assertFalse($identity->allows('/content/blogged'));
        self::assertSame(7, $identity->binding('blog_id'));
    }

    public function testFallbackKeepsPairingTokenInFragment(): void
    {
        $pairing = new SitePairing(
            'pair_1', 'tdsp_secret', 'blog', 'journal', 'https://blog.example.com', 'blog', [], ['/content/blog'], '2030-01-01T00:00:00Z'
        );
        $url = $pairing->installUrl('https://api.example.com');
        self::assertStringContainsString('/install#pairing_token=tdsp_secret', $url);
        self::assertStringNotContainsString('?pairing_token=', $url);
    }
}
