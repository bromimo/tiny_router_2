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

    public function test_body_is_stored_correctly(): void
    {
        $body = ['title' => 'Hello', 'content' => 'World'];
        $req = new Request(Method::POST, '/posts', [], $body, []);

        $this->assertSame($body, $req->body);
    }

    public function test_with_params_preserves_body(): void
    {
        $body = ['name' => 'test'];
        $req = new Request(Method::PUT, '/items/1', [], $body, ['content-type' => 'application/json']);
        $new = $req->withParams(['id' => '1']);

        $this->assertSame($body, $new->body);
        $this->assertSame(['id' => '1'], $new->params);
    }
}
