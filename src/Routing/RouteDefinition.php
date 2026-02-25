<?php

namespace TinyRouter\Routing;

use TinyRouter\Contract\MiddlewareInterface;

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

    public function middlewareInstance(MiddlewareInterface ...$instances): static
    {
        $this->route->addRouteMiddlewares($instances);
        return $this;
    }
}
