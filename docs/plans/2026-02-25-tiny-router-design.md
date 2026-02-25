# Tiny Router — Design Document

**Date:** 2026-02-25
**PHP:** 8.2+
**Dependencies:** none (zero external)

---

## Overview

A standalone, zero-dependency PHP 8.2 router library, distributable via Composer. Designed for minimal footprint and clean API.

---

## Features

- HTTP method routing (GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD)
- URL parameters with optional regex constraints: `/user/{id:\d+}`
- Route groups with prefix and shared middleware
- Named routes with URL generation
- Middleware pipeline (per-route and global)
- Static facade `Route::` for ergonomic usage
- `Router` class usable directly (for testing / DI)

---

## Architecture

### Directory structure

```
src/
  Http/
    Method.php              — enum backed by string
    Request.php             — readonly class, factory: Request::fromGlobals()
    Response.php            — mutable: code, headers, body + send()
  Routing/
    Route.php               — readonly value object (method, pattern, handler, name, middlewares)
    RouteDefinition.php     — fluent builder returned by Router::get/post/...
    RouteGroup.php          — builder for grouped routes (prefix + middlewares)
    RouteCollection.php     — stores and matches Route objects
    Router.php              — registers routes, dispatches request
    UrlGenerator.php        — generates URL from named route + params
  Facade/
    Route.php               — static facade delegating to singleton Router instance
  Contract/
    MiddlewareInterface.php — handle(Request, callable $next): Response
  Exception/
    HttpException.php           — base (code + message)
    NotFoundException.php       — 404
    MethodNotAllowedException.php — 405

tests/
  RouterTest.php
  UrlGeneratorTest.php
  MiddlewareTest.php
```

---

## Public API

### Registration via facade

```php
Route::get('/users', [UserController::class, 'index'])->name('users.index');
Route::post('/users', [UserController::class, 'store'])->name('users.store');
Route::get('/users/{id:\d+}', [UserController::class, 'show'])->name('users.show');

Route::group('/admin', function () {
    Route::get('/dashboard', fn(Request $req) => new Response('ok'))->name('admin.dashboard');
}, middlewares: [AuthMiddleware::class]);

Route::addMiddleware(LogMiddleware::class);

Route::dispatch(Request::fromGlobals())->send();

Route::url('users.show', ['id' => 42]); // → '/users/42'
```

### Handler signature

```php
// Closure
fn(Request $request): Response

// Invokable class
class MyHandler {
    public function __invoke(Request $request): Response { ... }
}

// [ClassName, 'method'] — instantiated by router
[MyController::class, 'index']
```

### Middleware interface

```php
interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
```

### Facade internals

```php
Route::swap(Router $router): void  // replace instance (for testing)
Route::getInstance(): Router
```

---

## Request lifecycle

```
Request::fromGlobals()
  → Route::dispatch($request)
    → RouteCollection::match(method, path)
        → not found       → throw NotFoundException (404)
        → wrong method    → throw MethodNotAllowedException (405)
        → found           → resolve handler + build middleware stack
    → execute pipeline: global middlewares → route middlewares → handler
    → return Response
  → $response->send()
```

---

## URL parameter matching

Pattern `{name}` matches `[^/]+` by default.
Pattern `{name:regex}` uses provided regex, e.g. `{id:\d+}`.

---

## Composer package

```json
{
  "name": "vendor/tiny-router",
  "description": "Miniature PHP 8.2 router",
  "type": "library",
  "require": { "php": "^8.2" },
  "require-dev": { "phpunit/phpunit": "^11" },
  "autoload": { "psr-4": { "TinyRouter\\": "src/" } },
  "autoload-dev": { "psr-4": { "TinyRouter\\Tests\\": "tests/" } }
}
```

**Namespace:** `TinyRouter\`

---

## Testing strategy

- Test `Router` directly (not the facade) for unit isolation
- Use `Route::swap(new Router())` in feature tests using the facade
- Cover: basic routing, parameter extraction, regex constraints, named route URL generation, middleware order, 404/405 exceptions, route groups
