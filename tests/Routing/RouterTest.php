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

    // --- Middleware pipeline tests ---

    public function test_global_middleware_runs_before_handler(): void
    {
        $log    = [];
        $router = new Router();

        $router->addMiddleware(new class($log) implements \TinyRouter\Contract\MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function handle(Request $req, callable $next): Response {
                $this->log[] = 'before';
                $res = $next($req);
                $this->log[] = 'after';
                return $res;
            }
        });

        $router->get('/test', function (Request $req) use (&$log) {
            $log[] = 'handler';
            return new Response('ok');
        });

        $router->dispatch(new Request(Method::GET, '/test', [], [], []));

        $this->assertSame(['before', 'handler', 'after'], $log);
    }

    public function test_route_middleware_runs_after_global(): void
    {
        $log    = [];
        $router = new Router();

        $router->addMiddleware(new class($log) implements \TinyRouter\Contract\MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function handle(Request $req, callable $next): Response {
                $this->log[] = 'global';
                return $next($req);
            }
        });

        $routeMw = new class($log) implements \TinyRouter\Contract\MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function handle(Request $req, callable $next): Response {
                $this->log[] = 'route';
                return $next($req);
            }
        };

        $def = $router->get('/test', function (Request $req) use (&$log) {
            $log[] = 'handler';
            return new Response('ok');
        });

        // Добавляем middleware-инстанс напрямую через Route
        $def->middlewareInstance($routeMw);

        $router->dispatch(new Request(Method::GET, '/test', [], [], []));

        $this->assertSame(['global', 'route', 'handler'], $log);
    }

    public function test_middleware_can_short_circuit(): void
    {
        $router = new Router();

        $router->addMiddleware(new class implements \TinyRouter\Contract\MiddlewareInterface {
            public function handle(Request $req, callable $next): Response {
                return new Response('blocked', 403);
            }
        });

        $router->get('/secret', fn() => new Response('secret'));

        $response = $router->dispatch(new Request(Method::GET, '/secret', [], [], []));
        $this->assertSame(403, $response->getStatus());
        $this->assertSame('blocked', $response->getBody());
    }
}
