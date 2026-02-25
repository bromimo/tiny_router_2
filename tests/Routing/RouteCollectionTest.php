<?php

namespace TinyRouter\Tests\Routing;

use PHPUnit\Framework\TestCase;
use TinyRouter\Exception\MethodNotAllowedException;
use TinyRouter\Exception\NotFoundException;
use TinyRouter\Http\Method;
use TinyRouter\Routing\Route;
use TinyRouter\Routing\RouteCollection;

class RouteCollectionTest extends TestCase
{
    private RouteCollection $col;

    protected function setUp(): void
    {
        $this->col = new RouteCollection();
        $this->col->add(new Route(Method::GET,  '/users',         fn() => null));
        $this->col->add(new Route(Method::POST, '/users',         fn() => null));
        $this->col->add(new Route(Method::GET,  '/users/{id}',    fn() => null));
        $this->col->add(new Route(Method::GET,  '/users/{id:\d+}/posts', fn() => null));
    }

    public function test_matches_static_route(): void
    {
        ['route' => $route, 'params' => $params] = $this->col->match(Method::GET, '/users');
        $this->assertSame('/users', $route->pattern);
        $this->assertSame([], $params);
    }

    public function test_matches_route_with_parameter(): void
    {
        ['route' => $route, 'params' => $params] = $this->col->match(Method::GET, '/users/42');
        $this->assertSame('/users/{id}', $route->pattern);
        $this->assertSame(['id' => '42'], $params);
    }

    public function test_matches_route_with_regex_constraint(): void
    {
        ['route' => $route, 'params' => $params] = $this->col->match(Method::GET, '/users/7/posts');
        $this->assertSame('/users/{id:\d+}/posts', $route->pattern);
        $this->assertSame(['id' => '7'], $params);
    }

    public function test_throws_not_found_when_no_match(): void
    {
        $this->expectException(NotFoundException::class);
        $this->col->match(Method::GET, '/nonexistent');
    }

    public function test_throws_method_not_allowed_when_path_exists_but_wrong_method(): void
    {
        $this->expectException(MethodNotAllowedException::class);
        $this->col->match(Method::DELETE, '/users');
    }

    public function test_method_not_allowed_includes_allowed_methods(): void
    {
        try {
            $this->col->match(Method::DELETE, '/users');
        } catch (MethodNotAllowedException $e) {
            $this->assertContains('GET', $e->getAllowedMethods());
            $this->assertContains('POST', $e->getAllowedMethods());
        }
    }
}
