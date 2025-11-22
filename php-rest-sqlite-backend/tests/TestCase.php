<?php

namespace Tests;

use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Psr7\Uri;

class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function createRequest(
        string $method,
        string $path,
        array $headers = ['HTTP_ACCEPT' => 'application/json'],
        array $cookies = [],
        array $serverParams = []
    ): Request {
        $uri = new Uri('', '', 80, $path);
        $handle = fopen('php://temp', 'w+');
        $stream = (new StreamFactory())->createStreamFromResource($handle);

        $h = new Headers();
        foreach ($headers as $name => $value) {
            $h->addHeader($name, $value);
        }

        return new Request($method, $uri, $h, $cookies, $serverParams, $stream);
    }

    protected function createResponse(): Response
    {
        return new Response();
    }

    protected function createRequestWithBody(string $method, string $path, array $data): Request
    {
        $request = $this->createRequest($method, $path);
        $request = $request->withParsedBody($data);
        return $request;
    }
}
