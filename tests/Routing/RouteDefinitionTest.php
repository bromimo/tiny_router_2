<?php

namespace TinyRouter\Tests\Routing;

use PHPUnit\Framework\TestCase;
use TinyRouter\Http\Method;
use TinyRouter\Routing\Route;
use TinyRouter\Routing\RouteDefinition;

class RouteDefinitionTest extends TestCase
{
    private function makeRoute(): Route
    {
        return new Route(Method::GET, '/users', fn() => null);
    }

    public function test_name_sets_route_name(): void
    {
        $route = $this->makeRoute();
        $def   = new RouteDefinition($route);

        $result = $def->name('users.index');

        $this->assertSame($def, $result);       // fluent
        $this->assertSame('users.index', $route->getName());
    }

    public function test_middleware_adds_to_route(): void
    {
        $route = $this->makeRoute();
        $def   = new RouteDefinition($route);

        $def->middleware('AuthMiddleware', 'LogMiddleware');

        $this->assertSame(['AuthMiddleware', 'LogMiddleware'], $route->getRouteMiddlewares());
    }

    public function test_chaining(): void
    {
        $route = $this->makeRoute();
        (new RouteDefinition($route))
            ->name('users.index')
            ->middleware('Auth');

        $this->assertSame('users.index', $route->getName());
        $this->assertSame(['Auth'], $route->getRouteMiddlewares());
    }
}
