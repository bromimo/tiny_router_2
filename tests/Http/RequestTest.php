<?php

namespace TinyRouter\Tests\Http;

use PHPUnit\Framework\TestCase;
use TinyRouter\Http\Method;
use TinyRouter\Http\Request;

class RequestTest extends TestCase
{
    public function test_constructor_stores_properties(): void
    {
        $req = new Request(
            method: Method::GET,
            path: '/users',
            query: ['page' => '1'],
            body: [],
            headers: ['accept' => 'application/json'],
        );

        $this->assertSame(Method::GET, $req->method);
        $this->assertSame('/users', $req->path);
        $this->assertSame(['page' => '1'], $req->query);
        $this->assertSame(['accept' => 'application/json'], $req->headers);
        $this->assertSame([], $req->params);
    }

    public function test_with_params_returns_new_instance_with_params(): void
    {
        $req = new Request(Method::GET, '/users/42', [], [], []);
        $new = $req->withParams(['id' => '42']);

        $this->assertNotSame($req, $new);
        $this->assertSame(['id' => '42'], $new->params);
        $this->assertSame([], $req->params); // original unchanged
    }
}
