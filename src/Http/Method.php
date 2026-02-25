<?php

namespace TinyRouter\Http;

enum Method: string
{
    case GET     = 'GET';
    case POST    = 'POST';
    case PUT     = 'PUT';
    case PATCH   = 'PATCH';
    case DELETE  = 'DELETE';
    case OPTIONS = 'OPTIONS';
    case HEAD    = 'HEAD';

    public static function fromString(string $method): self
    {
        return self::from(strtoupper($method));
    }
}
