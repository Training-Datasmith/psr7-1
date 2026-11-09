<?php

declare(strict_types=1);

namespace Tests\Nyholm\Psr7\Factory;

use Nyholm\Psr7\Factory\Psr17_Factory;
use PHPUnit\Framework\TestCase;

class Psr17_Factory_Test extends TestCase
{
    public function test_create_response()
    {
        $factory = new Psr17_Factory();
        $r = $factory->createResponse(200);
        $this->assertEquals('OK', $r->getReasonPhrase());

        $r = $factory->createResponse(200, '');
        $this->assertEquals('', $r->getReasonPhrase());

        $r = $factory->createResponse(200, 'Foo');
        $this->assertEquals('Foo', $r->getReasonPhrase());

        /*
         * Test for non-standard response codes
         */
        $r = $factory->createResponse(567);
        $this->assertEquals('', $r->getReasonPhrase());

        $r = $factory->createResponse(567, '');
        $this->assertEquals(567, $r->getStatusCode());
        $this->assertEquals('', $r->getReasonPhrase());

        $r = $factory->createResponse(567, 'Foo');
        $this->assertEquals(567, $r->getStatusCode());
        $this->assertEquals('Foo', $r->getReasonPhrase());
    }
}
