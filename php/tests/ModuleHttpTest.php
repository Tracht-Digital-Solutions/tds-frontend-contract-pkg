<?php

declare(strict_types=1);

namespace Tds\Frontend\Contract\Tests;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;
use Tds\Frontend\Contract\ModuleHttp;
use Tds\Frontend\Contract\UserContext;

final class ModuleHttpTest extends TestCase
{
    private function host(): object
    {
        return new class {
            use ModuleHttp;

            public function call(string $method, mixed ...$args): mixed
            {
                return self::$method(...$args);
            }
        };
    }

    private function user(bool $auth, bool $admin = false, array $perms = []): UserContext
    {
        $user = $this->createMock(UserContext::class);
        $user->method('isAuthenticated')->willReturn($auth);
        $user->method('isAdmin')->willReturn($admin);
        $user->method('has')->willReturnCallback(static fn (string $p): bool => in_array($p, $perms, true));
        return $user;
    }

    public function test_json_writes_a_typed_body(): void
    {
        $res = $this->host()->call('json', new Response(), ['ok' => true], 201);

        self::assertSame(201, $res->getStatusCode());
        self::assertSame('application/json', $res->getHeaderLine('Content-Type'));
        self::assertSame('{"ok":true}', (string) $res->getBody());
    }

    public function test_require_answers_401_then_403_then_null(): void
    {
        $h = $this->host();
        self::assertSame(401, $h->call('require', $this->user(false), 'x:read', new Response())->getStatusCode());
        self::assertSame(403, $h->call('require', $this->user(true), 'x:read', new Response())->getStatusCode());
        self::assertNull($h->call('require', $this->user(true, perms: ['x:read']), 'x:read', new Response()));
    }

    public function test_require_admin(): void
    {
        $h = $this->host();
        self::assertSame(401, $h->call('requireAdmin', $this->user(false), new Response())->getStatusCode());
        self::assertSame(403, $h->call('requireAdmin', $this->user(true), new Response())->getStatusCode());
        self::assertNull($h->call('requireAdmin', $this->user(true, admin: true), new Response()));
    }
}
