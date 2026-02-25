<?php

namespace TinyRouter\Http;

class Response
{
    public function __construct(
        private string $body    = '',
        private int    $status  = 200,
        private array  $headers = [],
    ) {}

    public function getBody(): string   { return $this->body; }
    public function getStatus(): int    { return $this->status; }
    public function getHeaders(): array { return $this->headers; }

    public function withHeader(string $name, string $value): static
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }
        echo $this->body;
    }
}
