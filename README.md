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

### Multi-Method Routes

Register the same handler for multiple HTTP methods:

```php
use TinyRouter\Http\Method;

Route::match([Method::GET, Method::POST], '/form', $handler)
    ->name('form.handle')
    ->middleware('auth');
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

### Middleware Aliases

Register short names for middleware classes:

```php
Route::addMiddlewareAlias('auth', AuthMiddleware::class);
Route::addMiddlewareAlias('cors', CorsMiddleware::class);

Route::get('/profile', $handler)->middleware('auth');
```

### Parameterized Middleware

Register middleware factories that accept parameters via `:` syntax:

```php
Route::addMiddlewareFactory('rate_limit', function (string $params): MiddlewareInterface {
    [$requests, $seconds] = explode(',', $params);
    return new RateLimitMiddleware((int)$requests, (int)$seconds);
});

Route::get('/api/data', $handler)->middleware('rate_limit:5,60');
```

Middleware resolution order:
1. `MiddlewareInterface` instance — used directly
2. Exact alias match (`addMiddlewareAlias`)
3. Parameterized factory (`alias:params` → `addMiddlewareFactory`)
4. Class name fallback — instantiated via `new`

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

### Fluent Group Builder

Build groups with a chainable API:

```php
Route::prefix('/api')
    ->middleware('auth')
    ->group(function () {
        Route::get('/users', $handler);
        Route::post('/users', $handler);
    });

// Middleware only, without prefix
Route::middleware('auth')->group(function () {
    Route::get('/profile', $handler);
    Route::get('/settings', $handler);
});

// Multiple middleware calls accumulate
Route::prefix('/admin')
    ->middleware('auth')
    ->middleware('admin')
    ->group(function () {
        Route::get('/dashboard', $handler);
    });
```

## Request Body Parsing

`Request::fromGlobals()` automatically parses request bodies for POST, PUT, PATCH and DELETE methods:

- `Content-Type: application/json` — decoded via `json_decode()`
- `Content-Type: application/x-www-form-urlencoded` — parsed via `parse_str()`

Parsed data is available in `$request->body`:

```php
Route::put('/users/{id}', function (Request $req) {
    $name = $req->body['name'];
    // ...
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

## Route Introspection

```php
// Get all registered routes
$routes = Route::routes();

foreach ($routes as $route) {
    echo $route->method->value . ' ' . $route->pattern;
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
    MiddlewareInterface.php       — handle(Request, callable $next): Response
  Exception/
    HttpException.php             — base exception
    NotFoundException.php         — 404
    MethodNotAllowedException.php — 405
  Facade/
    Route.php                     — static facade over singleton Router
  Http/
    Method.php                    — enum: GET POST PUT PATCH DELETE OPTIONS HEAD
    Request.php                   — readonly, factory: fromGlobals() with body parsing
    Response.php                  — body, status, headers + send()
  Routing/
    Route.php                     — value object: method, pattern, handler, middlewares
    RouteDefinition.php           — fluent builder returned by Router::get() etc.
    MultiRouteDefinition.php      — fluent builder for Router::match()
    RouteCollection.php           — stores and matches routes
    GroupDefinition.php           — value object returned by group()
    PendingRouteGroup.php         — fluent builder for prefix/middleware chains
    Router.php                    — registers routes, dispatches requests
    UrlGenerator.php              — generates URL from named route + params
```

## License

MIT
