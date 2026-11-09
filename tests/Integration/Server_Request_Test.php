<?php declare(strict_types=1);

namespace Tests\Nyholm\Psr7\Integration;

use Http\Psr7Test\ServerRequestIntegrationTest;
use Nyholm\Psr7\Server_Request;
use Psr\Http\Message\ServerRequestInterface;

class Server_Request_Test extends ServerRequestIntegrationTest
{
    public function createSubject(): ServerRequestInterface
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        return new Server_Request('GET', '/', [], null, '1.1', $_SERVER);
    }
}
