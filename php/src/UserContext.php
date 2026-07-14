<?php
declare(strict_types=1);

namespace Tds\Panel\Contract;

/**
 * The authenticated principal for the current request, exposed to modules
 * through the DI container (`$app->getContainer()->get(UserContext::class)`).
 * The BASE populates it from the verified RS256 JWT (JWKS); a module reads it
 * for RBAC + data scoping instead of re-implementing auth.
 *
 * For an unauthenticated request the container yields an anonymous context
 * ({@see isAuthenticated()} = false). Admin principals bypass permission checks
 * ({@see isAdmin()} = true); {@see activeCompanyId()} scopes a portal
 * (customer) request to one company/tenant.
 */
interface UserContext
{
    public function isAuthenticated(): bool;

    /** The app_user id, or null when anonymous. */
    public function userId(): ?int;

    /** The principal's email (JWT `email` claim), or null when absent/anonymous. */
    public function email(): ?string;

    /** True for an admin principal (bypasses permission checks). */
    public function isAdmin(): bool;

    /**
     * The permission keys held for the active company/scope.
     *
     * @return string[]
     */
    public function permissions(): array;

    /** True when the principal holds $permission (admins always true). */
    public function has(string $permission): bool;

    /** The active company/tenant id for a portal request, or null. */
    public function activeCompanyId(): ?int;
}
