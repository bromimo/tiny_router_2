<?php

namespace TinyRouter\Routing;

/** Fluent-обёртка над группой маршрутов, добавленных в рамках одного group(). */
final class GroupDefinition
{
    /** @param Route[] $routes */
    public function __construct(private readonly array $routes) {}

    /**
     * Добавить middleware ко всем маршрутам группы.
     * Принимает строковые имена классов или алиасы, зарегистрированные через addMiddlewareAlias().
     */
    public function middleware(string ...$aliases): static
    {
        foreach ($this->routes as $route) {
            $route->addRouteMiddlewares($aliases);
        }
        return $this;
    }
}
