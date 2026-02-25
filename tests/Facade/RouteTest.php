<?php

namespace TinyRouter\Tests\Facade;

use PHPUnit\Framework\TestCase;
use TinyRouter\Facade\Route;
use TinyRouter\Http\Method;
use TinyRouter\Http\Request;
use TinyRouter\Http\Response;
use TinyRouter\Routing\Router;

class RouteTest extends TestCase
{
    protected function setUp(): void
    {
        Route::swap(new Router());
    }

    private function get(string $path): Request
    {
        return new Request(Method::GET, $path, [], [], []);
    }

    public function test_facade_registers_and_dispatches_route(): void
    {
        Route::get('/hello', fn() => new Response('facade'));

        $response = Route::dispatch($this->get('/hello'));
        $this->assertSame('facade', $response->getBody());
    }

    public function test_facade_named_route_url(): void
    {
        Route::get('/users/{id}', fn() => new Response())->name('users.show');

        $this->assertSame('/users/3', Route::url('users.show', ['id' => 3]));
    }

    public function test_swap_replaces_instance(): void
    {
        Route::get('/old', fn() => new Response('old'));

        Route::swap(new Router());
        Route::get('/new', fn() => new Response('new'));

        $this->expectException(\TinyRouter\Exception\NotFoundException::class);
        Route::dispatch($this->get('/old'));
    }

    public function test_get_instance_returns_same_router(): void
    {
        $a = Route::getInstance();
        $b = Route::getInstance();
        $this->assertSame($a, $b);
    }
}
