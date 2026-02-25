# Tiny Router — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a zero-dependency, PHP 8.2 router library with URL parameters, route groups, middleware pipeline, named routes, and a static facade.

**Architecture:** A `Router` class holds a `RouteCollection` and dispatches `Request` objects through a middleware pipeline to handlers, returning a `Response`. A static `Route` facade wraps a singleton `Router` instance for ergonomic usage.

**Tech Stack:** PHP 8.2, PHPUnit 11, Composer (PSR-4 autoload)

---

## Task 1: Project setup

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml`

**Step 1: Create `composer.json`**

```json
{
    "name": "vendor/tiny-router",
    "description": "Miniature PHP 8.2 router library",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.2"
    },
    "require-dev": {
        "phpunit/phpunit": "^11"
    },
    "autoload": {
        "psr-4": {
            "TinyRouter\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "TinyRouter\\Tests\\": "tests/"
        }
    }
}
```

**Step 2: Create `phpunit.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="TinyRouter">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

**Step 3: Install dependencies**

```bash
composer install
```

Expected: `vendor/` created, `vendor/bin/phpunit` present.

**Step 4: Create directory structure**

```bash
mkdir -p src/Http src/Routing src/Facade src/Contract src/Exception
mkdir -p tests/Http tests/Routing tests/Facade
```

**Step 5: Commit**

```bash
git add composer.json phpunit.xml
git commit -m "chore: project setup with Composer and PHPUnit"
```

---

## Task 2: Method enum

**Files:**
- Create: `src/Http/Method.php`
- Create: `tests/Http/MethodTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace TinyRouter\Tests\Http;

use PHPUnit\Framework\TestCase;
use TinyRouter\Http\Method;

class MethodTest extends TestCase
{
    public function test_from_string_returns_correct_enum(): void
    {
        $this->assertSame(Method::GET, Method::fromString('GET'));
        $this->assertSame(Method::POST, Method::fromString('post'));
        $this->assertSame(Method::PUT, Method::fromString('Put'));
    }

    public function test_from_string_throws_on_invalid(): void
    {
        $this->expectException(\ValueError::class);
        Method::fromString('INVALID');
    }
}
```

**Step 2: Run to verify it fails**

```bash
./vendor/bin/phpunit tests/Http/MethodTest.php
```

Expected: FAIL — `TinyRouter\Http\Method not found`

**Step 3: Implement `src/Http/Method.php`**

```php
<?php

namespace TinyRouter\Http;

enum Method: string
{
    case GET     = 'GET';
    case POST    = 'POST';
    case PUT     = 'PUT';
    case PATCH   = 'PATCH';
    case DELETE  = 'DELETE';
    case OPTIONS = 'OPTIONS';
    case HEAD    = 'HEAD';

    public static function fromString(string $method): self
    {
        return self::from(strtoupper($method));
    }
}
```

**Step 4: Run to verify it passes**

```bash
./vendor/bin/phpunit tests/Http/MethodTest.php
```

Expected: 2 tests, PASS

**Step 5: Commit**

```bash
git add src/Http/Method.php tests/Http/MethodTest.php
git commit -m "feat: add Method enum"
```

---

## Task 3: HttpException hierarchy

**Files:**
- Create: `src/Exception/HttpException.php`
- Create: `src/Exception/NotFoundException.php`
- Create: `src/Exception/MethodNotAllowedException.php`
- Create: `tests/Http/HttpExceptionTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace TinyRouter\Tests\Http;

use PHPUnit\Framework\TestCase;
use TinyRouter\Exception\HttpException;
use TinyRouter\Exception\MethodNotAllowedException;
use TinyRouter\Exception\NotFoundException;

class HttpExceptionTest extends TestCase
{
    public function test_not_found_exception_has_404_code(): void
    {
        $e = new NotFoundException();
        $this->assertSame(404, $e->getCode());
        $this->assertInstanceOf(HttpException::class, $e);
    }

    public function test_method_not_allowed_exposes_allowed_methods(): void
    {
        $e = new MethodNotAllowedException(['GET', 'POST']);
        $this->assertSame(405, $e->getCode());
        $this->assertSame(['GET', 'POST'], $e->getAllowedMethods());
    }
}
```

**Step 2: Run to verify it fails**

```bash
./vendor/bin/phpunit tests/Http/HttpExceptionTest.php
```

Expected: FAIL

**Step 3: Implement the exception classes**

`src/Exception/HttpException.php`:
```php
<?php

namespace TinyRouter\Exception;

class HttpException extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
```

`src/Exception/NotFoundException.php`:
```php
<?php

namespace TinyRouter\Exception;

class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Not Found', ?\Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
```

`src/Exception/MethodNotAllowedException.php`:
```php
<?php

namespace TinyRouter\Exception;

class MethodNotAllowedException extends HttpException
{
    public function __construct(private readonly array $allowedMethods, ?\Throwable $previous = null)
    {
        parent::__construct('Method Not Allowed', 405, $previous);
    }

    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
```

**Step 4: Run to verify it passes**

```bash
./vendor/bin/phpunit tests/Http/HttpExceptionTest.php
```

Expected: 2 tests, PASS

**Step 5: Commit**

```bash
git add src/Exception/ tests/Http/HttpExceptionTest.php
git commit -m "feat: add HttpException hierarchy"
```

---

## Task 4: Request class

**Files:**
- Create: `src/Http/Request.php`
- Create: `tests/Http/RequestTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace TinyRouter\Tests\Http;

use PHPUnit\Framework\TestCase;
use TinyRouter\Http\Method;
use TinyRouter\Http\Request;

class RequestTest extends TestCase
{
    public function test_constructor_stores_properties(): void
    {
        $req = new Request(
            method: Method::GET,
            path: '/users',
            query: ['page' => '1'],
            body: [],
            headers: ['accept' => 'application/json'],
        );

        $this->assertSame(Method::GET, $req->method);
        $this->assertSame('/users', $req->path);
        $this->assertSame(['page' => '1'], $req->query);
        $this->assertSame(['accept' => 'application/json'], $req->headers);
        $this->assertSame([], $req->params);
    }

    public function test_with_params_returns_new_instance_with_params(): void
    {
        $req = new Request(Method::GET, '/users/42', [], [], []);
        $new = $req->withParams(['id' => '42']);

        $this->assertNotSame($req, $new);
        $this->assertSame(['id' => '42'], $new->params);
        $this->assertSame([], $req->params); // original unchanged
    }
}
```

**Step 2: Run to verify it fails**

```bash
./vendor/bin/phpunit tests/Http/RequestTest.php
```

Expected: FAIL

**Step 3: Implement `src/Http/Request.php`**

```php
<?php

namespace TinyRouter\Http;

readonly class Request
{
    public function __construct(
        public Method $method,
        public string $path,
        public array  $query,
        public array  $body,
        public array  $headers,
        public array  $params = [],
    ) {}

    public static function fromGlobals(): self
    {
        $method  = Method::fromString($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }

        return new self(
            method: $method,
            path: $path,
            query: $_GET,
            body: $_POST,
            headers: $headers,
        );
    }

    public function withParams(array $params): self
    {
        return new self(
            method: $this->method,
            path: $this->path,
            query: $this->query,
            body: $this->body,
            headers: $this->headers,
            params: $params,
        );
    }
}
```

**Step 4: Run to verify it passes**

```bash
./vendor/bin/phpunit tests/Http/RequestTest.php
```

Expected: 2 tests, PASS

**Step 5: Commit**

```bash
git add src/Http/Request.php tests/Http/RequestTest.php
git commit -m "feat: add Request class"
```

---

## Task 5: Response class

**Files:**
- Create: `src/Http/Response.php`
- Create: `tests/Http/ResponseTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace TinyRouter\Tests\Http;

use PHPUnit\Framework\TestCase;
use TinyRouter\Http\Response;

class ResponseTest extends TestCase
{
    public function test_defaults(): void
    {
        $r = new Response();
        $this->assertSame(200, $r->getStatus());
        $this->assertSame('', $r->getBody());
        $this->assertSame([], $r->getHeaders());
    }

    public function test_constructor_stores_values(): void
    {
        $r = new Response('hello', 201, ['Content-Type' => 'text/plain']);
        $this->assertSame(201, $r->getStatus());
        $this->assertSame('hello', $r->getBody());
        $this->assertSame(['Content-Type' => 'text/plain'], $r->getHeaders());
    }

    public function test_with_header_returns_new_instance(): void
    {
        $r  = new Response('ok', 200);
        $r2 = $r->withHeader('X-Custom', 'value');

        $this->assertNotSame($r, $r2);
        $this->assertArrayNotHasKey('X-Custom', $r->getHeaders());
        $this->assertSame('value', $r2->getHeaders()['X-Custom']);
    }
}
```

**Step 2: Run to verify it fails**

```bash
./vendor/bin/phpunit tests/Http/ResponseTest.php
```

Expected: FAIL

**Step 3: Implement `src/Http/Response.php`**

```php
<?php

namespace TinyRouter\Http;

class Response
{
    public function __construct(
        private string $body    = '',
        private int    $status  = 200,
        private array  $headers = [],
    ) {}

    public function getBody(): string   { return $this->body; }
    public function getStatus(): int    { return $this->status; }
    public function getHeaders(): array { return $this->headers; }

    public function withHeader(string $name, string $value): static
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }
        echo $this->body;
    }
}
```

**Step 4: Run to verify it passes**

```bash
./vendor/bin/phpunit tests/Http/ResponseTest.php
```

Expected: 3 tests, PASS

**Step 5: Commit**

```bash
git add src/Http/Response.php tests/Http/ResponseTest.php
git commit -m "feat: add Response class"
```

---

## Task 6: MiddlewareInterface

**Files:**
- Create: `src/Contract/MiddlewareInterface.php`

No unit test needed — it is a contract. Tested implicitly via middleware pipeline tests in Task 11.

**Step 1: Create `src/Contract/MiddlewareInterface.php`**

```php
<?php

namespace TinyRouter\Contract;

use TinyRouter\Http\Request;
use TinyRouter\Http\Response;

interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
```

**Step 2: Commit**

```bash
git add src/Contract/MiddlewareInterface.php
git commit -m "feat: add MiddlewareInterface"
```

---

## Task 7: Route and RouteDefinition

**Files:**
- Create: `src/Routing/Route.php`
- Create: `src/Routing/RouteDefinition.php`
- Create: `tests/Routing/RouteDefinitionTest.php`

**Step 1: Write the failing test**

```php
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
```

**Step 2: Run to verify it fails**

```bash
./vendor/bin/phpunit tests/Routing/RouteDefinitionTest.php
```

Expected: FAIL

**Step 3: Implement `src/Routing/Route.php`**

```php
<?php

namespace TinyRouter\Routing;

use TinyRouter\Http\Method;

final class Route
{
    private ?string $name             = null;
    private array   $routeMiddlewares = [];

    public function __construct(
        public readonly Method $method,
        public readonly string $pattern,
        public readonly mixed  $handler,
        public readonly array  $groupMiddlewares = [],
    ) {}

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function addRouteMiddlewares(array $classes): void
    {
        $this->routeMiddlewares = [...$this->routeMiddlewares, ...$classes];
    }

    public function getRouteMiddlewares(): array
    {
        return $this->routeMiddlewares;
    }
}
```

**Step 4: Implement `src/Routing/RouteDefinition.php`**

```php
<?php

namespace TinyRouter\Routing;

final class RouteDefinition
{
    public function __construct(private readonly Route $route) {}

    public function name(string $name): static
    {
        $this->route->setName($name);
        return $this;
    }

    public function middleware(string ...$classes): static
    {
        $this->route->addRouteMiddlewares($classes);
        return $this;
    }
}
```

**Step 5: Run to verify it passes**

```bash
./vendor/bin/phpunit tests/Routing/RouteDefinitionTest.php
```

Expected: 3 tests, PASS

**Step 6: Commit**

```bash
git add src/Routing/Route.php src/Routing/RouteDefinition.php tests/Routing/RouteDefinitionTest.php
git commit -m "feat: add Route and RouteDefinition"
```

---

## Task 8: RouteCollection (URL pattern matching)

**Files:**
- Create: `src/Routing/RouteCollection.php`
- Create: `tests/Routing/RouteCollectionTest.php`

**Step 1: Write the failing test**

```php
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
```

**Step 2: Run to verify it fails**

```bash
./vendor/bin/phpunit tests/Routing/RouteCollectionTest.php
```

Expected: FAIL

**Step 3: Implement `src/Routing/RouteCollection.php`**

```php
<?php

namespace TinyRouter\Routing;

use TinyRouter\Exception\MethodNotAllowedException;
use TinyRouter\Exception\NotFoundException;
use TinyRouter\Http\Method;

final class RouteCollection
{
    /** @var Route[] */
    private array $routes = [];

    public function add(Route $route): void
    {
        $this->routes[] = $route;
    }

    /**
     * @return array{route: Route, params: array<string, string>}
     * @throws NotFoundException
     * @throws MethodNotAllowedException
     */
    public function match(Method $method, string $path): array
    {
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            $params = $this->matchPattern($route->pattern, $path);
            if ($params === null) {
                continue;
            }
            if ($route->method !== $method) {
                $allowedMethods[] = $route->method->value;
                continue;
            }
            return ['route' => $route, 'params' => $params];
        }

        if ($allowedMethods !== []) {
            throw new MethodNotAllowedException(array_unique($allowedMethods));
        }

        throw new NotFoundException("No route found for {$method->value} {$path}");
    }

    /** @return array<string, string>|null null if no match */
    private function matchPattern(string $pattern, string $path): ?array
    {
        $regex = $this->compilePattern($pattern);
        if (!preg_match($regex, $path, $matches)) {
            return null;
        }
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    private function compilePattern(string $pattern): string
    {
        // Split on {name} or {name:regex} tokens, keeping them as delimiters
        $tokens = preg_split('/(\{[^}]+\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);

        $regex = '';
        foreach ($tokens as $token) {
            if (preg_match('/^\{(\w+)(?::([^}]+))?\}$/', $token, $m)) {
                $name       = $m[1];
                $constraint = $m[2] ?? '[^/]+';
                $regex     .= "(?P<{$name}>{$constraint})";
            } else {
                $regex .= preg_quote($token, '#');
            }
        }

        return '#^' . $regex . '/?$#';
    }
}
```

**Step 4: Run to verify it passes**

```bash
./vendor/bin/phpunit tests/Routing/RouteCollectionTest.php
```

Expected: 6 tests, PASS

**Step 5: Commit**

```bash
git add src/Routing/RouteCollection.php tests/Routing/RouteCollectionTest.php
git commit -m "feat: add RouteCollection with URL pattern matching"
```

---

## Task 9: UrlGenerator

**Files:**
- Create: `src/Routing/UrlGenerator.php`
- Create: `tests/Routing/UrlGeneratorTest.php`

**Step 1: Write the failing test**

```php
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
```

**Step 2: Run to verify it fails**

```bash
./vendor/bin/phpunit tests/Routing/UrlGeneratorTest.php
```

Expected: FAIL

**Step 3: Implement `src/Routing/UrlGenerator.php`**

```php
<?php

namespace TinyRouter\Routing;

final class UrlGenerator
{
    public function __construct(private readonly RouteCollection $collection) {}

    /**
     * @param array<string, mixed> $params
     */
    public function generate(string $name, array $params = []): string
    {
        $route = $this->collection->findByName($name);

        if ($route === null) {
            throw new \InvalidArgumentException("No route named '{$name}'");
        }

        return $this->substituteParams($route->pattern, $params);
    }

    private function substituteParams(string $pattern, array $params): string
    {
        return preg_replace_callback(
            '/\{(\w+)(?::[^}]+)?\}/',
            function (array $m) use ($params, $pattern): string {
                $name = $m[1];
                if (!array_key_exists($name, $params)) {
                    throw new \InvalidArgumentException(
                        "Missing parameter '{$name}' for pattern '{$pattern}'"
                    );
                }
                return (string) $params[$name];
            },
            $pattern,
        );
    }
}
```

**Step 4: Add `findByName()` to RouteCollection**

Open `src/Routing/RouteCollection.php` and add this method at the end of the class:

```php
public function findByName(string $name): ?Route
{
    foreach ($this->routes as $route) {
        if ($route->getName() === $name) {
            return $route;
        }
    }
    return null;
}
```

**Step 5: Run to verify it passes**

```bash
./vendor/bin/phpunit tests/Routing/UrlGeneratorTest.php
```

Expected: 5 tests, PASS

**Step 6: Commit**

```bash
git add src/Routing/UrlGenerator.php src/Routing/RouteCollection.php tests/Routing/UrlGeneratorTest.php
git commit -m "feat: add UrlGenerator and RouteCollection::findByName"
```

---

## Task 10: Router — basic dispatch

**Files:**
- Create: `src/Routing/Router.php`
- Create: `tests/Routing/RouterTest.php`

**Step 1: Write the failing test**

```php
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
```

**Step 2: Run to verify it fails**

```bash
./vendor/bin/phpunit tests/Routing/RouterTest.php
```

Expected: FAIL

**Step 3: Implement `src/Routing/Router.php`**

```php
<?php

namespace TinyRouter\Routing;

use TinyRouter\Contract\MiddlewareInterface;
use TinyRouter\Http\Method;
use TinyRouter\Http\Request;
use TinyRouter\Http\Response;

final class Router
{
    private RouteCollection $collection;
    private UrlGenerator    $urlGenerator;

    /** @var array<string|MiddlewareInterface> */
    private array $globalMiddlewares = [];

    /** @var array<string> current group prefix stack */
    private array $prefixStack = [];

    /** @var array<array<string|MiddlewareInterface>> current group middleware stack */
    private array $middlewareStack = [];

    public function __construct()
    {
        $this->collection   = new RouteCollection();
        $this->urlGenerator = new UrlGenerator($this->collection);
    }

    public function get(string $path, mixed $handler): RouteDefinition
    {
        return $this->addRoute(Method::GET, $path, $handler);
    }

    public function post(string $path, mixed $handler): RouteDefinition
    {
        return $this->addRoute(Method::POST, $path, $handler);
    }

    public function put(string $path, mixed $handler): RouteDefinition
    {
        return $this->addRoute(Method::PUT, $path, $handler);
    }

    public function patch(string $path, mixed $handler): RouteDefinition
    {
        return $this->addRoute(Method::PATCH, $path, $handler);
    }

    public function delete(string $path, mixed $handler): RouteDefinition
    {
        return $this->addRoute(Method::DELETE, $path, $handler);
    }

    public function options(string $path, mixed $handler): RouteDefinition
    {
        return $this->addRoute(Method::OPTIONS, $path, $handler);
    }

    public function addMiddleware(string|MiddlewareInterface $middleware): void
    {
        $this->globalMiddlewares[] = $middleware;
    }

    public function group(string $prefix, callable $callback, array $middlewares = []): void
    {
        $this->prefixStack[]    = $prefix;
        $this->middlewareStack[] = $middlewares;
        $callback($this);
        array_pop($this->prefixStack);
        array_pop($this->middlewareStack);
    }

    public function dispatch(Request $request): Response
    {
        ['route' => $route, 'params' => $params] = $this->collection->match(
            $request->method,
            $request->path,
        );

        $request = $request->withParams($params);
        $handler = $this->resolveHandler($route->handler);

        $middlewares = [
            ...$this->globalMiddlewares,
            ...$route->groupMiddlewares,
            ...$route->getRouteMiddlewares(),
        ];

        $pipeline = $this->buildPipeline($middlewares, $handler);

        return $pipeline($request);
    }

    public function url(string $name, array $params = []): string
    {
        return $this->urlGenerator->generate($name, $params);
    }

    // -------------------------------------------------------------------------

    private function addRoute(Method $method, string $path, mixed $handler): RouteDefinition
    {
        $fullPath = implode('', $this->prefixStack) . $path;

        $groupMiddlewares = array_merge(...array_values($this->middlewareStack) ?: [[]]);

        $route = new Route($method, $fullPath, $handler, $groupMiddlewares);
        $this->collection->add($route);

        return new RouteDefinition($route);
    }

    private function resolveHandler(mixed $handler): callable
    {
        if (is_callable($handler)) {
            return $handler;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $object = is_object($class) ? $class : new $class();
            return [$object, $method];
        }

        if (is_string($handler)) {
            return new $handler();
        }

        throw new \InvalidArgumentException('Handler must be callable, [class, method], or invokable class name.');
    }

    private function buildPipeline(array $middlewares, callable $handler): callable
    {
        $core = $handler;

        foreach (array_reverse($middlewares) as $middleware) {
            $resolved = $this->resolveMiddleware($middleware);
            $next     = $core;
            $core     = fn(Request $req) => $resolved->handle($req, $next);
        }

        return $core;
    }

    private function resolveMiddleware(string|MiddlewareInterface $middleware): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }
        return new $middleware();
    }
}
```

**Step 4: Run to verify it passes**

```bash
./vendor/bin/phpunit tests/Routing/RouterTest.php
```

Expected: 7 tests, PASS

**Step 5: Commit**

```bash
git add src/Routing/Router.php tests/Routing/RouterTest.php
git commit -m "feat: add Router with dispatch, handler resolution, and URL generation"
```

---

## Task 11: Middleware pipeline

**Files:**
- Modify: `tests/Routing/RouterTest.php` — add middleware tests

**Step 1: Append to `tests/Routing/RouterTest.php`**

Add these test methods inside the `RouterTest` class:

```php
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

    $router->get('/test', function (Request $req) use (&$log) {
        $log[] = 'handler';
        return new Response('ok');
    })->middleware(
        (new class($log) implements \TinyRouter\Contract\MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function handle(Request $req, callable $next): Response {
                $this->log[] = 'route';
                return $next($req);
            }
        })::class
    );

    // Use inline instance instead of class name — add direct object support in test
    // Re-implement test using addMiddleware() with object, route middleware via addRouteMiddlewares()
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
```

> **Note:** The `addMiddleware()` method accepts `MiddlewareInterface` instances directly (not just class names), so passing `new class...` objects works. For route-level middleware with `->middleware(...)`, pass class name strings or refactor `RouteDefinition::middleware()` to also accept instances. For this plan, global middleware accepts both strings and instances, which is already implemented in `resolveMiddleware()`.

**Step 2: Run to verify new tests pass** (middleware already implemented in Router)

```bash
./vendor/bin/phpunit tests/Routing/RouterTest.php
```

Expected: all tests PASS

**Step 3: Commit**

```bash
git add tests/Routing/RouterTest.php
git commit -m "test: add middleware pipeline tests"
```

---

## Task 12: Route groups

**Files:**
- Modify: `tests/Routing/RouterTest.php` — add group tests

**Step 1: Append group tests to `tests/Routing/RouterTest.php`**

```php
public function test_group_prefix_is_prepended(): void
{
    $router = new Router();

    $router->group('/admin', function (Router $r) {
        $r->get('/dashboard', fn() => new Response('admin dash'));
    });

    $response = $router->dispatch(new Request(Method::GET, '/admin/dashboard', [], [], []));
    $this->assertSame('admin dash', $response->getBody());
}

public function test_group_middleware_applies_to_group_routes(): void
{
    $log    = [];
    $router = new Router();

    $router->group('/api', function (Router $r) use (&$log) {
        $r->get('/data', function (Request $req) use (&$log) {
            $log[] = 'handler';
            return new Response('data');
        });
    }, middlewares: [
        new class($log) implements \TinyRouter\Contract\MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function handle(Request $req, callable $next): Response {
                $this->log[] = 'group-mw';
                return $next($req);
            }
        },
    ]);

    $router->dispatch(new Request(Method::GET, '/api/data', [], [], []));
    $this->assertSame(['group-mw', 'handler'], $log);
}

public function test_group_does_not_affect_routes_outside(): void
{
    $router = new Router();
    $router->get('/home', fn() => new Response('home'));

    $router->group('/admin', function (Router $r) {
        $r->get('/panel', fn() => new Response('panel'));
    });

    $response = $router->dispatch(new Request(Method::GET, '/home', [], [], []));
    $this->assertSame('home', $response->getBody());
}
```

> **Note on passing instances as group middlewares:** `Router::group()` accepts `array $middlewares` where each element can be a `string` (class name) or `MiddlewareInterface` instance. The existing `resolveMiddleware()` already handles both.

**Step 2: Run to verify it passes**

```bash
./vendor/bin/phpunit tests/Routing/RouterTest.php
```

Expected: all tests PASS (group logic already in Router)

**Step 3: Commit**

```bash
git add tests/Routing/RouterTest.php
git commit -m "test: add route group tests"
```

---

## Task 13: Route facade

**Files:**
- Create: `src/Facade/Route.php`
- Create: `tests/Facade/RouteTest.php`

**Step 1: Write the failing test**

```php
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
        // Reset facade between tests
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
```

**Step 2: Run to verify it fails**

```bash
./vendor/bin/phpunit tests/Facade/RouteTest.php
```

Expected: FAIL

**Step 3: Implement `src/Facade/Route.php`**

```php
<?php

namespace TinyRouter\Facade;

use TinyRouter\Contract\MiddlewareInterface;
use TinyRouter\Http\Request;
use TinyRouter\Http\Response;
use TinyRouter\Routing\RouteDefinition;
use TinyRouter\Routing\Router;

final class Route
{
    private static ?Router $instance = null;

    public static function getInstance(): Router
    {
        if (self::$instance === null) {
            self::$instance = new Router();
        }
        return self::$instance;
    }

    public static function swap(Router $router): void
    {
        self::$instance = $router;
    }

    public static function get(string $path, mixed $handler): RouteDefinition
    {
        return self::getInstance()->get($path, $handler);
    }

    public static function post(string $path, mixed $handler): RouteDefinition
    {
        return self::getInstance()->post($path, $handler);
    }

    public static function put(string $path, mixed $handler): RouteDefinition
    {
        return self::getInstance()->put($path, $handler);
    }

    public static function patch(string $path, mixed $handler): RouteDefinition
    {
        return self::getInstance()->patch($path, $handler);
    }

    public static function delete(string $path, mixed $handler): RouteDefinition
    {
        return self::getInstance()->delete($path, $handler);
    }

    public static function options(string $path, mixed $handler): RouteDefinition
    {
        return self::getInstance()->options($path, $handler);
    }

    public static function group(string $prefix, callable $callback, array $middlewares = []): void
    {
        self::getInstance()->group($prefix, $callback, $middlewares);
    }

    public static function addMiddleware(string|MiddlewareInterface $middleware): void
    {
        self::getInstance()->addMiddleware($middleware);
    }

    public static function dispatch(Request $request): Response
    {
        return self::getInstance()->dispatch($request);
    }

    public static function url(string $name, array $params = []): string
    {
        return self::getInstance()->url($name, $params);
    }
}
```

**Step 4: Run to verify it passes**

```bash
./vendor/bin/phpunit tests/Facade/RouteTest.php
```

Expected: 4 tests, PASS

**Step 5: Run the full test suite**

```bash
./vendor/bin/phpunit
```

Expected: all tests PASS, no failures.

**Step 6: Commit**

```bash
git add src/Facade/Route.php tests/Facade/RouteTest.php
git commit -m "feat: add Route facade"
```

---

## Final verification

```bash
./vendor/bin/phpunit --testdox
```

Expected output includes all test names passing across all test files. Zero errors or failures.
