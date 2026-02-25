<?php

namespace TinyRouter\Tests\Routing;

use PHPUnit\Framework\TestCase;
use TinyRouter\Http\Method;
use TinyRouter\Routing\Route;
use TinyRouter\Routing\RouteCollection;
use TinyRouter\Routing\UrlGenerator;

class UrlGeneratorTest extends TestCase
{
    private UrlGenerator $gen;

    protected function setUp(): void
    {
        $col = new RouteCollection();

        $r1 = new Route(Method::GET, '/users', fn() => null);
        $r1->setName('users.index');
        $col->add($r1);

        $r2 = new Route(Method::GET, '/users/{id:\d+}', fn() => null);
        $r2->setName('users.show');
        $col->add($r2);

        $r3 = new Route(Method::GET, '/posts/{slug}', fn() => null);
        $r3->setName('posts.show');
        $col->add($r3);

        $this->gen = new UrlGenerator($col);
    }

    public function test_generates_static_url(): void
    {
        $this->assertSame('/users', $this->gen->generate('users.index'));
    }

    public function test_generates_url_with_param(): void
    {
        $this->assertSame('/users/42', $this->gen->generate('users.show', ['id' => 42]));
    }

    public function test_generates_url_with_string_param(): void
    {
        $this->assertSame('/posts/my-article', $this->gen->generate('posts.show', ['slug' => 'my-article']));
    }

    public function test_throws_on_unknown_route_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->gen->generate('nonexistent');
    }

    public function test_throws_on_missing_param(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->gen->generate('users.show', []);
    }
}
