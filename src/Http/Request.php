<?php

namespace TinyRouter\Http;

readonly class Request
{
    public function __construct(
        public Method $method,
        public string $path,
        public array  $query,
        public array  $body,
        public array  $headers,
        public array  $params = [],
    ) {}

    public static function fromGlobals(): self
    {
        $method  = Method::fromString($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }

        return new self(
            method: $method,
            path: $path,
            query: $_GET,
            body: $_POST,
            headers: $headers,
        );
    }

    public function withParams(array $params): self
    {
        return new self(
            method: $this->method,
            path: $this->path,
            query: $this->query,
            body: $this->body,
            headers: $this->headers,
            params: $params,
        );
    }
}
