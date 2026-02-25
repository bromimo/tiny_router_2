<?php

namespace TinyRouter\Tests\Http;

use PHPUnit\Framework\TestCase;
use TinyRouter\Http\Response;

class ResponseTest extends TestCase
{
    public function test_defaults(): void
    {
        $r = new Response();
        $this->assertSame(200, $r->getStatus());
        $this->assertSame('', $r->getBody());
        $this->assertSame([], $r->getHeaders());
    }

    public function test_constructor_stores_values(): void
    {
        $r = new Response('hello', 201, ['Content-Type' => 'text/plain']);
        $this->assertSame(201, $r->getStatus());
        $this->assertSame('hello', $r->getBody());
        $this->assertSame(['Content-Type' => 'text/plain'], $r->getHeaders());
    }

    public function test_with_header_returns_new_instance(): void
    {
        $r  = new Response('ok', 200);
        $r2 = $r->withHeader('X-Custom', 'value');

        $this->assertNotSame($r, $r2);
        $this->assertArrayNotHasKey('X-Custom', $r->getHeaders());
        $this->assertSame('value', $r2->getHeaders()['X-Custom']);
    }
}
