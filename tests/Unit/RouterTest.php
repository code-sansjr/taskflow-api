<?php

declare(strict_types=1);

namespace TaskFlow\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TaskFlow\Core\HttpException;
use TaskFlow\Core\Request;
use TaskFlow\Core\Response;
use TaskFlow\Core\Router;

final class RouterTest extends TestCase
{
    public function testDispatchesRouteWithParameters(): void
    {
        $router = new Router();
        $router->get('/api/items/{id}', static function (Request $request, array $params): Response {
            return Response::json(['id' => $params['id']]);
        });

        $response = $router->dispatch(new Request('GET', '/api/items/42/'));
        $payload = json_decode($response->body, true);

        self::assertSame(200, $response->status);
        self::assertSame(['id' => '42'], $payload);
    }

    public function testUnknownRouteReturnsNotFound(): void
    {
        $this->expectException(HttpException::class);

        (new Router())->dispatch(new Request('GET', '/api/unknown'));
    }

    public function testWrongMethodReturnsMethodNotAllowed(): void
    {
        $router = new Router();
        $router->get('/api/items', static fn (): Response => Response::json([]));

        try {
            $router->dispatch(new Request('POST', '/api/items'));
            self::fail('The router should have rejected the method.');
        } catch (HttpException $exception) {
            self::assertSame(405, $exception->status);
            self::assertSame(['GET'], $exception->details['allowed']);
        }
    }
}
