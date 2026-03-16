<?php

namespace TinyRouter\Tests\Routing;

use PHPUnit\Framework\TestCase;
use TinyRouter\Http\Method;
use TinyRouter\Http\Request;
use TinyRouter\Http\Response;
use TinyRouter\Routing\MultiRouteDefinition;
use TinyRouter\Routing\Route;
use TinyRouter\Routing\RouteDefinition;
use TinyRouter\Routing\Router;

class MultiRouteDefinitionTest extends TestCase
{
    public function test_name_sets_name_on_all_routes(): void
    {
        $routes = [
            new Route(Method::GET, '/test', fn() => null),
            new Route(Method::POST, '/test', fn() => null),
        ];

        $definitions = array_map(fn(Route $r) => new RouteDefinition($r), $routes);

        $multi = new MultiRouteDefinition($definitions);
        $result = $multi->name('test.route');

        $this->assertSame($multi, $result); // fluent
        foreach ($routes as $route) {
            $this->assertSame('test.route', $route->getName());
        }
    }

    public function test_middleware_adds_to_all_routes(): void
    {
        $routes = [
            new Route(Method::PUT, '/item', fn() => null),
            new Route(Method::PATCH, '/item', fn() => null),
        ];

        $definitions = array_map(fn(Route $r) => new RouteDefinition($r), $routes);

        (new MultiRouteDefinition($definitions))->middleware('AuthMiddleware', 'LogMiddleware');

        foreach ($routes as $route) {
            $this->assertSame(['AuthMiddleware', 'LogMiddleware'], $route->getRouteMiddlewares());
        }
    }

    public function test_chaining(): void
    {
        $routes = [
            new Route(Method::GET, '/res', fn() => null),
            new Route(Method::DELETE, '/res', fn() => null),
        ];

        $definitions = array_map(fn(Route $r) => new RouteDefinition($r), $routes);

        (new MultiRouteDefinition($definitions))
            ->name('resource')
            ->middleware('Auth');

        foreach ($routes as $route) {
            $this->assertSame('resource', $route->getName());
            $this->assertSame(['Auth'], $route->getRouteMiddlewares());
        }
    }

    public function test_router_match_registers_multiple_methods(): void
    {
        $router = new Router();
        $router->match(
            [Method::GET, Method::POST],
            '/form',
            fn(Request $req) => new Response('ok:' . $req->method->value),
        );

        $getResponse = $router->dispatch(new Request(Method::GET, '/form', [], [], []));
        $this->assertSame('ok:GET', $getResponse->getBody());

        $postResponse = $router->dispatch(new Request(Method::POST, '/form', [], [], []));
        $this->assertSame('ok:POST', $postResponse->getBody());
    }

    public function test_router_match_returns_multi_definition_with_fluent_api(): void
    {
        $router = new Router();
        $multi = $router->match(
            [Method::PUT, Method::PATCH],
            '/items/{id}',
            fn(Request $req) => new Response('updated'),
        );

        $multi->name('items.update')->middleware('Auth');

        $routes = $router->routes();
        foreach ($routes as $route) {
            $this->assertSame('items.update', $route->getName());
            $this->assertSame(['Auth'], $route->getRouteMiddlewares());
        }
    }

    public function test_router_match_works_inside_group(): void
    {
        $router = new Router();

        $router->group('/api', function (Router $r) {
            $r->match(
                [Method::GET, Method::HEAD],
                '/health',
                fn() => new Response('alive'),
            );
        });

        $response = $router->dispatch(new Request(Method::GET, '/api/health', [], [], []));
        $this->assertSame('alive', $response->getBody());
    }
}
