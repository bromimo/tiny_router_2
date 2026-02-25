<?php

namespace TinyRouter\Contract;

use TinyRouter\Http\Request;
use TinyRouter\Http\Response;

interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
