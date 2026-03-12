<?php

namespace TinyRouter\Routing;

/** Pending-группа маршрутов: накапливает prefix и middleware до вызова group(). */
final class PendingRouteGroup
{
    /** @param list<string> $middlewares */
    public function __construct(
        private readonly Router $router,
        private readonly string $prefix = '',
        private readonly array  $middlewares = [],
    ) {}

    /** Установить префикс группы.
     * @param string $prefix
     * @return static
     */
    public function prefix(string $prefix): static
    {
        return new static($this->router, $prefix, $this->middlewares);
    }

    /** Добавить middleware к группе.
     * @param string|list<string> $middleware
     * @return static
     */
    public function middleware(string|array $middleware): static
    {
        return new static($this->router, $this->prefix, [...$this->middlewares, ...(array)$middleware]);
    }

    /** Зарегистрировать группу маршрутов.
     * @param callable $callback
     * @return GroupDefinition
     */
    public function group(callable $callback): GroupDefinition
    {
        return $this->router->group($this->prefix, $callback, $this->middlewares);
    }
}
