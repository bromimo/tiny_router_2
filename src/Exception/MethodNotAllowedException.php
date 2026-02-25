<?php

namespace TinyRouter\Exception;

class MethodNotAllowedException extends HttpException
{
    public function __construct(private readonly array $allowedMethods, ?\Throwable $previous = null)
    {
        parent::__construct('Method Not Allowed', 405, $previous);
    }

    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
