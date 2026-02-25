<?php

namespace TinyRouter\Tests\Http;

use PHPUnit\Framework\TestCase;
use TinyRouter\Exception\HttpException;
use TinyRouter\Exception\MethodNotAllowedException;
use TinyRouter\Exception\NotFoundException;

class HttpExceptionTest extends TestCase
{
    public function test_not_found_exception_has_404_code(): void
    {
        $e = new NotFoundException();
        $this->assertSame(404, $e->getCode());
        $this->assertInstanceOf(HttpException::class, $e);
    }

    public function test_method_not_allowed_exposes_allowed_methods(): void
    {
        $e = new MethodNotAllowedException(['GET', 'POST']);
        $this->assertSame(405, $e->getCode());
        $this->assertSame(['GET', 'POST'], $e->getAllowedMethods());
    }
}
