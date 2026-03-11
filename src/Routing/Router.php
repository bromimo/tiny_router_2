<?php

namespace TinyRouter\Routing;

use TinyRouter\Contract\MiddlewareInterface;
use TinyRouter\Http\Method;
use TinyRouter\Http\Request;
use TinyRouter\Http\Response;

class Router
{
    private RouteCollection $collection;
    private UrlGenerator    $urlGenerator;

    /** @var array<string|MiddlewareInterface> */
    private array $globalMiddlewares = [];

    /** @var array<string> current group prefix stack */
    private array $prefixStack = [];

    /** @var array<array<string|MiddlewareInterface>> current group middleware stack */
    private array $middlewareStack = [];

    /** @var array<string, callable(string, Request): mixed> */
    private array $typeResolvers = [];

    /** @var array<string, string> Алиасы middleware: 'alias' => ClassName */
    private array $middlewareAliases = [];

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

    /**
     * Register a type resolver for dependency injection.
     * When a controller method parameter's type extends $baseClass,
     * the callable $resolver is invoked to produce the argument value.
     *
     * @param string $baseClass Fully-qualified class or interface name
     * @param callable(string, Request): mixed $resolver Receives the concrete type name and the current Request
     */
    public function addTypeResolver(string $baseClass, callable $resolver): void
    {
        $this->typeResolvers[$baseClass] = $resolver;
    }

    /**
     * Register a middleware alias.
     *
     * @param string $alias  Short name, e.g. 'auth:api'
     * @param string $class  Fully-qualified class name
     */
    public function addMiddlewareAlias(string $alias, string $class): void
    {
        $this->middlewareAliases[$alias] = $class;
    }

    public function addMiddleware(string|MiddlewareInterface $middleware): void
    {
        $this->globalMiddlewares[] = $middleware;
    }

    public function group(string $prefix, callable $callback, array $middlewares = []): GroupDefinition
    {
        $this->prefixStack[]     = $prefix;
        $this->middlewareStack[] = $middlewares;
        $snapshot = $this->collection->count();
        $callback($this);
        array_pop($this->prefixStack);
        array_pop($this->middlewareStack);
        return new GroupDefinition($this->collection->since($snapshot));
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

        $diHandler = empty($this->typeResolvers)
            ? $handler
            : function (Request $req) use ($handler): Response {
                return $handler(...$this->resolveParams($handler, $req));
            };

        $pipeline = $this->buildPipeline($middlewares, $diHandler);

        return $pipeline($request);
    }

    public function url(string $name, array $params = []): string
    {
        return $this->urlGenerator->generate($name, $params);
    }

    // -------------------------------------------------------------------------

    private function addRoute(Method $method, string $path, mixed $handler): RouteDefinition
    {
        $prefix   = implode('', $this->prefixStack);
        $fullPath = '/' . ltrim(rtrim($prefix, '/') . '/' . ltrim($path, '/'), '/');

        $groupMiddlewares = array_merge(...($this->middlewareStack ?: [[]]));

        $route = new Route($method, $fullPath, $handler, $groupMiddlewares);
        $this->collection->add($route);

        return new RouteDefinition($route);
    }

    protected function resolveHandler(mixed $handler): callable
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

    /**
     * Build the argument list for a handler using registered type resolvers and route params.
     *
     * @param callable $handler
     * @param Request  $request
     * @return array<mixed>
     */
    private function resolveParams(callable $handler, Request $request): array
    {
        $reflection = is_array($handler)
            ? new \ReflectionMethod($handler[0], $handler[1])
            : new \ReflectionFunction(\Closure::fromCallable($handler));

        $args = [];

        foreach ($reflection->getParameters() as $param) {
            $type     = $param->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;

            if ($typeName === Request::class || $typeName === self::class) {
                $args[] = $request;
                continue;
            }

            if ($typeName && !$type->isBuiltin()) {
                $resolver = $this->findResolver($typeName);
                if ($resolver !== null) {
                    $args[] = $resolver($typeName, $request);
                    continue;
                }
                // No resolver — instantiate without arguments
                $args[] = new $typeName();
                continue;
            }

            // Scalar — read from route params by parameter name
            $value = $request->params[$param->getName()] ?? null;

            $args[] = match ($typeName) {
                'int'   => (int) $value,
                'float' => (float) $value,
                'bool'  => (bool) $value,
                default => (string) ($value ?? ''),
            };
        }

        return $args;
    }

    /**
     * Find a registered resolver for the given type, checking the class hierarchy.
     *
     * @param string $type Fully-qualified class name
     * @return callable|null
     */
    private function findResolver(string $type): ?callable
    {
        foreach ($this->typeResolvers as $baseClass => $resolver) {
            if ($type === $baseClass || is_subclass_of($type, $baseClass)) {
                return $resolver;
            }
        }
        return null;
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
        $class = $this->middlewareAliases[$middleware] ?? $middleware;
        return new $class();
    }
}
