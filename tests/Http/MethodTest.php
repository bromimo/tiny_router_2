<?php

namespace TinyRouter\Tests\Http;

use PHPUnit\Framework\TestCase;
use TinyRouter\Http\Method;

class MethodTest extends TestCase
{
    public function test_from_string_returns_correct_enum(): void
    {
        $this->assertSame(Method::GET, Method::fromString('GET'));
        $this->assertSame(Method::POST, Method::fromString('post'));
        $this->assertSame(Method::PUT, Method::fromString('Put'));
    }

    public function test_from_string_throws_on_invalid(): void
    {
        $this->expectException(\ValueError::class);
        Method::fromString('INVALID');
    }
}
