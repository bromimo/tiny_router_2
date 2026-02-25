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
