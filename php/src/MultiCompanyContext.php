<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

/**
 * Optional companion to {@see UserContext}: the FULL set of companies the
 * principal belongs to, not just the one currently acted as.
 *
 * ### Why this is a separate interface
 *
 * `UserContext` exposes only {@see UserContext::activeCompanyId()}, which is
 * the right answer for data scoping — a request reads and writes within one
 * tenant. It is the wrong answer for two things the panel now needs: naming
 * the user's company in the profile menu (they may belong to several, and the
 * admin-only company directory is not readable by a portal user), and offering
 * a company switcher at all.
 *
 * Adding `companyIds()` to `UserContext` itself would NOT be an additive minor
 * despite this package's 1.x promise: adding a method to an interface breaks
 * every *implementer*, which here means the base's `JwtUserContext` and
 * `AnonymousUserContext` plus the test doubles in all thirteen extensions.
 * Same reasoning, and the same shape, as {@see ApiDocSource} and
 * {@see NotificationSource}: a capability a class opts into, probed with
 * `instanceof`.
 *
 * ```php
 * $user = $container->get(UserContext::class);
 * $ids = $user instanceof MultiCompanyContext ? $user->companyIds() : [];
 * ```
 *
 * ### What it is NOT
 *
 * Not an authorization surface. Membership is not permission — a caller still
 * has to check {@see UserContext::has()} for the active company before
 * returning anything about it, and an admin (who bypasses permissions and may
 * act as any company) legitimately reports **no** memberships here.
 */
interface MultiCompanyContext
{
    /**
     * Every company id the principal is a member of, in the order the token
     * carries them — the first is the primary/default company.
     *
     * Empty for an anonymous principal, and empty for an admin: an admin's
     * reach is "any company", which is not the same thing as belonging to one,
     * and returning "all companies" here would turn a convenience accessor
     * into an unbounded directory read.
     *
     * @return list<int>
     */
    public function companyIds(): array;
}
