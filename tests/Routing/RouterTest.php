<?php

namespace TinyRouter\Tests\Routing;

use PHPUnit\Framework\TestCase;
use TinyRouter\Exception\MethodNotAllowedException;
use TinyRouter\Exception\NotFoundException;
use TinyRouter\Http\Method;
use TinyRouter\Http\Request;
use TinyRouter\Http\Response;
use TinyRouter\Routing\Router;

class RouterTest extends TestCase
{
    private function get(string $path): Request
    {
        return new Request(Method::GET, $path, [], [], []);
    }

    private function post(string $path): Request
    {
        return new Request(Method::POST, $path, [], [], []);
    }

    public function test_dispatches_closure_handler(): void
    {
        $router = new Router();
        $router->get('/hello', fn(Request $req) => new Response('world'));

        $response = $router->dispatch($this->get('/hello'));
        $this->assertSame('world', $response->getBody());
    }

    public function test_dispatches_invokable_class(): void
    {
        $router = new Router();
        $router->get('/ping', new class {
            public function __invoke(Request $req): Response {
                return new Response('pong');
            }
        });

        $response = $router->dispatch($this->get('/ping'));
        $this->assertSame('pong', $response->getBody());
    }

    public function test_dispatches_class_method_array(): void
    {
        $handler = new class {
            public function index(Request $req): Response {
                return new Response('index');
            }
        };

        $router = new Router();
        $router->get('/items', [$handler, 'index']);

        $response = $router->dispatch($this->get('/items'));
        $this->assertSame('index', $response->getBody());
    }

    public function test_injects_url_params_into_request(): void
    {
        $router = new Router();
        $router->get('/users/{id}', fn(Request $req) => new Response($req->params['id']));

        $response = $router->dispatch($this->get('/users/99'));
        $this->assertSame('99', $response->getBody());
    }

    public function test_throws_not_found_for_unknown_path(): void
    {
        $router = new Router();
        $this->expectException(NotFoundException::class);
        $router->dispatch($this->get('/nothing'));
    }

    public function test_throws_method_not_allowed(): void
    {
        $router = new Router();
        $router->get('/form', fn() => new Response());
        $this->expectException(MethodNotAllowedException::class);
        $router->dispatch($this->post('/form'));
    }

    public function test_url_generates_named_route(): void
    {
        $router = new Router();
        $router->get('/users/{id}', fn() => new Response())->name('users.show');

        $this->assertSame('/users/5', $router->url('users.show', ['id' => 5]));
    }
}
