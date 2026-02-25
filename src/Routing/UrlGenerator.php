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
