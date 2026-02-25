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
