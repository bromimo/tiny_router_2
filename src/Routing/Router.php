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
        $this->prefixStack[]     = $prefix;
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

        $groupMiddlewares = array_merge(...($this->middlewareStack ?: [[]]));

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
