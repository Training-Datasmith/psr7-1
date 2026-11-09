<?php declare(strict_types=1);

namespace Tests\Nyholm\Psr7;

use Nyholm\Psr7\Server_Request;
use Nyholm\Psr7\Uploaded_File;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Nyholm\Psr7\Server_Request
 */
class Server_Request_Test extends TestCase
{
    public function test_uploaded__files()
    {
        $request1 = new Server_Request('GET', '/');

        $files = [
            'file' => new Uploaded_File('test', 123, \UPLOAD_ERR_OK),
        ];

        $request2 = $request1->withUploadedFiles($files);

        $this->assertNotSame($request2, $request1);
        $this->assertSame([], $request1->getUploadedFiles());
        $this->assertSame($files, $request2->getUploadedFiles());
    }

    public function test_server_params()
    {
        $params = ['name' => 'value'];

        $request = new Server_Request('GET', '/', [], null, '1.1', $params);
        $this->assertSame($params, $request->getServerParams());
    }

    public function test_cookie_params()
    {
        $request1 = new Server_Request('GET', '/');

        $params = ['name' => 'value'];

        $request2 = $request1->withCookieParams($params);

        $this->assertNotSame($request2, $request1);
        $this->assertEmpty($request1->getCookieParams());
        $this->assertSame($params, $request2->getCookieParams());
    }

    public function test_query_params()
    {
        $request0 = new Server_Request('GET', '/');
        $this->assertEmpty($request0->getQueryParams());

        $request1 = new Server_Request('GET', '/?foo=bar');
        $params = ['name' => 'value'];
        $request2 = $request1->withQueryParams($params);

        $this->assertNotSame($request2, $request1);
        $this->assertSame(['foo' => 'bar'], $request1->getQueryParams());
        $this->assertSame($params, $request2->getQueryParams());
    }

    public function test_parsed_body()
    {
        $request1 = new Server_Request('GET', '/');

        $params = ['name' => 'value'];

        $request2 = $request1->withParsedBody($params);

        $this->assertNotSame($request2, $request1);
        $this->assertEmpty($request1->getParsedBody());
        $this->assertSame($params, $request2->getParsedBody());
    }

    public function test_attributes()
    {
        $request1 = new Server_Request('GET', '/');

        $request2 = $request1->withAttribute('name', 'value');
        $request3 = $request2->withAttribute('other', 'otherValue');
        $request4 = $request3->withoutAttribute('other');
        $request5 = $request3->withoutAttribute('unknown');

        $this->assertNotSame($request2, $request1);
        $this->assertNotSame($request3, $request2);
        $this->assertNotSame($request4, $request3);
        $this->assertNotSame($request5, $request4);

        $this->assertEmpty($request1->getAttributes());
        $this->assertEmpty($request1->getAttribute('name'));
        $this->assertEquals(
            'something',
            $request1->getAttribute('name', 'something'),
            'Should return the default value'
        );

        $this->assertEquals('value', $request2->getAttribute('name'));
        $this->assertEquals(['name' => 'value'], $request2->getAttributes());
        $this->assertEquals(['name' => 'value', 'other' => 'otherValue'], $request3->getAttributes());
        $this->assertEquals(['name' => 'value'], $request4->getAttributes());
    }

    public function test_null_attribute()
    {
        $request = (new Server_Request('GET', '/'))->withAttribute('name', null);

        $this->assertSame(['name' => null], $request->getAttributes());
        $this->assertNull($request->getAttribute('name', 'different-default'));

        $request_without_attribute = $request->withoutAttribute('name');

        $this->assertSame([], $request_without_attribute->getAttributes());
        $this->assertSame('different-default', $request_without_attribute->getAttribute('name', 'different-default'));
    }
}
