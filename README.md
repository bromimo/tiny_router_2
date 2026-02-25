# Tiny Router

A zero-dependency PHP 8.2 router library with URL parameters, route groups, middleware pipeline, named routes, and a static facade.

## Requirements

- PHP 8.2+

## Installation

```bash
composer require bromimo/tiny-router
```

## Quick Start

```php
<?php
require 'vendor/autoload.php';

use TinyRouter\Facade\Route;
use TinyRouter\Http\Request;
use TinyRouter\Http\Response;

Route::get('/', fn() => new Response('Hello, world!'));
Route::get('/users/{id:\d+}', fn(Request $req) => new Response($req->params['id']));

Route::dispatch(Request::fromGlobals())->send();
```

## HTTP Methods

```php
Route::get('/path', $handler);
Route::post('/path', $handler);
Route::put('/path', $handler);
Route::patch('/path', $handler);
Route::delete('/path', $handler);
Route::options('/path', $handler);
```

## URL Parameters

```php
// Any value
Route::get('/users/{id}', fn(Request $req) => new Response($req->params['id']));

// With regex constraint
Route::get('/users/{id:\d+}', $handler);
Route::get('/posts/{slug:[a-z-]+}', $handler);
```

## Handler Formats

```php
// Closure
Route::get('/a', fn(Request $req) => new Response('ok'));

// Invokable class instance
Route::get('/b', new MyHandler());

// [ClassName, method] — instantiated by the router
Route::get('/c', [UserController::class, 'index']);
```

## Named Routes & URL Generation

```php
Route::get('/users/{id}', $handler)->name('users.show');

$url = Route::url('users.show', ['id' => 42]); // → '/users/42'
```

## Middleware

Implement `MiddlewareInterface`:

```php
use TinyRouter\Contract\MiddlewareInterface;
use TinyRouter\Http\Request;
use TinyRouter\Http\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (empty($request->headers['authorization'])) {
            return new Response('Unauthorized', 401);
        }
        return $next($request);
    }
}
```

Register middleware:

```php
// Global — runs for every route
Route::addMiddleware(AuthMiddleware::class);
Route::addMiddleware(new AuthMiddleware());

// Per-route (class name)
Route::get('/profile', $handler)->middleware(AuthMiddleware::class);

// Per-route (instance)
Route::get('/profile', $handler)->middlewareInstance(new AuthMiddleware());
```

Execution order: **global → group → route → handler**.

## Route Groups

```php
Route::group('/admin', function () {
    Route::get('/dashboard', fn() => new Response('Dashboard'));
    Route::get('/users', fn() => new Response('Users'));
}, middlewares: [AuthMiddleware::class]);
```

Groups support nesting:

```php
Route::group('/api', function () {
    Route::group('/v1', function () {
        Route::get('/status', fn() => new Response('ok'));
        // → GET /api/v1/status
    });
});
```

## Response

```php
// Body + status
new Response('Not Found', 404);

// With headers
(new Response('{"ok":true}', 200))
    ->withHeader('Content-Type', 'application/json');

// Send to browser
$response->send();
```

## Error Handling

```php
use TinyRouter\Exception\NotFoundException;
use TinyRouter\Exception\MethodNotAllowedException;

try {
    Route::dispatch(Request::fromGlobals())->send();
} catch (NotFoundException $e) {
    (new Response('Not Found', 404))->send();
} catch (MethodNotAllowedException $e) {
    (new Response('Method Not Allowed', 405))
        ->withHeader('Allow', implode(', ', $e->getAllowedMethods()))
        ->send();
}
```

## Using Router Directly (without facade)

Useful for testing or dependency injection:

```php
use TinyRouter\Routing\Router;
use TinyRouter\Http\Request;
use TinyRouter\Http\Method;

$router = new Router();
$router->get('/hello', fn(Request $req) => new Response('world'));

$request  = new Request(Method::GET, '/hello', [], [], []);
$response = $router->dispatch($request);
```

## Testing

In tests, reset the facade between test cases:

```php
use TinyRouter\Facade\Route;
use TinyRouter\Routing\Router;

protected function setUp(): void
{
    Route::swap(new Router());
}
```

## Running Tests

```bash
composer install
./vendor/bin/phpunit
```

## Architecture

```
src/
  Contract/
    MiddlewareInterface.php     — handle(Request, callable $next): Response
  Exception/
    HttpException.php           — base exception
    NotFoundException.php       — 404
    MethodNotAllowedException.php — 405
  Facade/
    Route.php                   — static facade over singleton Router
  Http/
    Method.php                  — enum: GET POST PUT PATCH DELETE OPTIONS HEAD
    Request.php                 — readonly, factory: Request::fromGlobals()
    Response.php                — body, status, headers + send()
  Routing/
    Route.php                   — value object: method, pattern, handler, middlewares
    RouteDefinition.php         — fluent builder returned by Router::get() etc.
    RouteCollection.php         — stores and matches routes
    Router.php                  — registers routes, dispatches requests
    UrlGenerator.php            — generates URL from named route + params
```

## License

MIT
