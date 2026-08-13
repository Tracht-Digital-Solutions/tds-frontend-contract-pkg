<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract\Tests;

use PHPUnit\Framework\TestCase;
use Tds\Frontend\Contract\MultiCompanyContext;
use Tds\Frontend\Contract\UserContext;

/**
 * A `UserContext` that knows nothing about the companion interface — i.e.
 * every implementation that existed before it.
 */
class PlainUserContext implements UserContext
{
    public function isAuthenticated(): bool
    {
        return true;
    }

    public function userId(): ?int
    {
        return 5;
    }

    public function email(): ?string
    {
        return 'user@example.com';
    }

    public function isAdmin(): bool
    {
        return false;
    }

    public function permissions(): array
    {
        return ['tickets:read'];
    }

    public function has(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function activeCompanyId(): ?int
    {
        return 7;
    }
}

final class MultiCompanyUserContext extends PlainUserContext implements MultiCompanyContext
{
    public function companyIds(): array
    {
        return [7, 9];
    }
}

/**
 * The point of shipping this as a companion interface rather than a method on
 * `UserContext` is that pre-existing implementations keep compiling. That is
 * what these tests pin — if someone "tidies" `companyIds()` into `UserContext`
 * later, the first test stops compiling, which is the intended alarm.
 */
final class MultiCompanyContextTest extends TestCase
{
    public function testAPreExistingUserContextStillSatisfiesTheInterface(): void
    {
        $user = new PlainUserContext();

        self::assertInstanceOf(UserContext::class, $user);
        self::assertNotInstanceOf(MultiCompanyContext::class, $user);
    }

    public function testCallersProbeWithInstanceofAndDegradeToEmpty(): void
    {
        // The documented consumption pattern, exactly as the customers
        // extension uses it.
        $resolve = static fn (UserContext $u): array =>
            $u instanceof MultiCompanyContext ? $u->companyIds() : [];

        self::assertSame([], $resolve(new PlainUserContext()));
        self::assertSame([7, 9], $resolve(new MultiCompanyUserContext()));
    }

    public function testTheActiveCompanyIsOneOfTheMemberships(): void
    {
        // Not enforced by the interface, but it is the invariant every caller
        // assumes when it labels the active company from this list.
        $user = new MultiCompanyUserContext();

        self::assertContains($user->activeCompanyId(), $user->companyIds());
    }
}
