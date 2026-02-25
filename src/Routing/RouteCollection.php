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

    public function findByName(string $name): ?Route
    {
        foreach ($this->routes as $route) {
            if ($route->getName() === $name) {
                return $route;
            }
        }
        return null;
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
