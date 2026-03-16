<?php

namespace TinyRouter\Routing;

use TinyRouter\Contract\MiddlewareInterface;

final class MultiRouteDefinition
{
    /** @param RouteDefinition[] $definitions */
    public function __construct(private readonly array $definitions) {}

    public function name(string $name): static
    {
        foreach ($this->definitions as $definition) {
            $definition->name($name);
        }
        return $this;
    }

    public function middleware(string ...$classes): static
    {
        foreach ($this->definitions as $definition) {
            $definition->middleware(...$classes);
        }
        return $this;
    }

    public function middlewareInstance(MiddlewareInterface ...$instances): static
    {
        foreach ($this->definitions as $definition) {
            $definition->middlewareInstance(...$instances);
        }
        return $this;
    }
}
