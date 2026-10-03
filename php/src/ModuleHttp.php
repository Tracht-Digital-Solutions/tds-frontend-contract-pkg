<?php

declare(strict_types=1);

namespace Tds\Frontend\Contract;

use Psr\Http\Message\ResponseInterface as Response;

/**
 * The three helpers every module's routes use, in one place.
 *
 * Each extension had copied them from the template: fifteen byte-identical
 * `json()`, twelve `require()`, five `requireAdmin()`. A copy that drifts is
 * how one module would start answering 403 where the others answer 401, or
 * forget the JSON content type. `use ModuleHttp;` inside the module class
 * keeps every existing `self::json(...)` / `self::require(...)` call working.
 */
trait ModuleHttp
{
    /** JSON body with status. Throws on unencodable data rather than sending "false". */
    private static function json(Response $res, mixed $data, int $status = 200): Response
    {
        $res->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR));
        return $res->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    /** 401 without a session, 403 without `$permission`, null when allowed. */
    private static function require(UserContext $user, string $permission, Response $res): ?Response
    {
        if (!$user->isAuthenticated()) {
            return self::json($res, ['error' => 'Unauthorized'], 401);
        }
        if (!$user->has($permission)) {
            return self::json($res, ['error' => 'Forbidden'], 403);
        }
        return null;
    }

    /** 401 without a session, 403 for a non-admin, null when allowed. */
    private static function requireAdmin(UserContext $user, Response $res): ?Response
    {
        if (!$user->isAuthenticated()) {
            return self::json($res, ['error' => 'Unauthorized'], 401);
        }
        if (!$user->isAdmin()) {
            return self::json($res, ['error' => 'Forbidden'], 403);
        }
        return null;
    }
}
