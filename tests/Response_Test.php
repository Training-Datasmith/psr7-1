<?php

namespace Tests\Nyholm\Psr7;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * @covers \Nyholm\Psr7\Message_Trait
 * @covers \Nyholm\Psr7\Response
 */
class Response_Test extends TestCase
{
    public function test_default_constructor()
    {
        $r = new Response();
        $this->assertSame(200, $r->getStatusCode());
        $this->assertSame('1.1', $r->getProtocolVersion());
        $this->assertSame('OK', $r->getReasonPhrase());
        $this->assertSame([], $r->getHeaders());
        $this->assertInstanceOf(StreamInterface::class, $r->getBody());
        $this->assertSame('', (string) $r->getBody());
    }

    public function test_default_constructor_seek_body()
    {
        $r = new Response(200, [], 'Hello');
        $this->assertSame(200, $r->getStatusCode());
        $this->assertInstanceOf(StreamInterface::class, $r->getBody());
        $this->assertSame('Hello', $r->getBody()->getContents());
    }

    public function test_can_construct_with_status_code()
    {
        $r = new Response(404);
        $this->assertSame(404, $r->getStatusCode());
        $this->assertSame('Not Found', $r->getReasonPhrase());
    }

    public function test_can_construct_with_undefined_status_code()
    {
        $r = new Response(999);
        $this->assertSame(999, $r->getStatusCode());
        $this->assertSame('', $r->getReasonPhrase());
    }

    public function test_can_construct_with_status_code_and_empty_reason()
    {
        $r = new Response(404, [], null, '1.1', '');
        $this->assertSame(404, $r->getStatusCode());
        $this->assertSame('', $r->getReasonPhrase());
    }

    public function test_constructor_does_not_read_stream_body()
    {
        $body = $this->getMockBuilder(StreamInterface::class)->getMock();
        $body->expects($this->never())
            ->method('__toString');

        $r = new Response(200, [], $body);
        $this->assertSame($body, $r->getBody());
    }

    public function test_status_can_be_numeric_string()
    {
        $r = new Response('404');
        $r2 = $r->withStatus('201');
        $this->assertSame(404, $r->getStatusCode());
        $this->assertSame('Not Found', $r->getReasonPhrase());
        $this->assertSame(201, $r2->getStatusCode());
        $this->assertSame('Created', $r2->getReasonPhrase());
    }

    public function test_can_construct_with_headers()
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $this->assertSame(['Foo' => ['Bar']], $r->getHeaders());
        $this->assertSame('Bar', $r->getHeaderLine('Foo'));
        $this->assertSame(['Bar'], $r->getHeader('Foo'));
    }

    public function test_can_construct_with_headers_as_array()
    {
        $r = new Response(200, [
            'Foo' => ['baz', 'bar'],
        ]);
        $this->assertSame(['Foo' => ['baz', 'bar']], $r->getHeaders());
        $this->assertSame('baz, bar', $r->getHeaderLine('Foo'));
        $this->assertSame(['baz', 'bar'], $r->getHeader('Foo'));
    }

    public function test_can_construct_with_body()
    {
        $r = new Response(200, [], 'baz');
        $this->assertInstanceOf(StreamInterface::class, $r->getBody());
        $this->assertSame('baz', (string) $r->getBody());
    }

    public function test_null_body()
    {
        $r = new Response(200, [], null);
        $this->assertInstanceOf(StreamInterface::class, $r->getBody());
        $this->assertSame('', (string) $r->getBody());
    }

    public function test_falsey_body()
    {
        $r = new Response(200, [], '0');
        $this->assertInstanceOf(StreamInterface::class, $r->getBody());
        $this->assertSame('0', (string) $r->getBody());
    }

    public function test_can_construct_with_reason()
    {
        $r = new Response(200, [], null, '1.1', 'bar');
        $this->assertSame('bar', $r->getReasonPhrase());

        $r = new Response(200, [], null, '1.1', '0');
        $this->assertSame('0', $r->getReasonPhrase(), 'Falsey reason works');
    }

    public function test_can_construct_with_protocol_version()
    {
        $r = new Response(200, [], null, '1000');
        $this->assertSame('1000', $r->getProtocolVersion());
    }

    public function test_with_status_code_and_no_reason()
    {
        $r = (new Response())->withStatus(201);
        $this->assertSame(201, $r->getStatusCode());
        $this->assertSame('Created', $r->getReasonPhrase());
    }

    public function test_with_status_code_and_reason()
    {
        $r = (new Response())->withStatus(201, 'Foo');
        $this->assertSame(201, $r->getStatusCode());
        $this->assertSame('Foo', $r->getReasonPhrase());

        $r = (new Response())->withStatus(201, '0');
        $this->assertSame(201, $r->getStatusCode());
        $this->assertSame('0', $r->getReasonPhrase(), 'Falsey reason works');
    }

    public function test_with_protocol_version()
    {
        $r = (new Response())->withProtocolVersion('1000');
        $this->assertSame('1000', $r->getProtocolVersion());
    }

    public function test_same_instance_when_same_protocol()
    {
        $r = new Response();
        $this->assertSame($r, $r->withProtocolVersion('1.1'));
    }

    public function test_with_body()
    {
        $b = (new \Nyholm\Psr7\Factory\Psr17_Factory())->createStream('0');
        $r = (new Response())->withBody($b);
        $this->assertInstanceOf(StreamInterface::class, $r->getBody());
        $this->assertSame('0', (string) $r->getBody());
    }

    public function test_same_instance_when_same_body()
    {
        $r = new Response();
        $b = $r->getBody();
        $this->assertSame($r, $r->withBody($b));
    }

    public function test_with_header()
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withHeader('baZ', 'Bam');
        $this->assertSame(['Foo' => ['Bar']], $r->getHeaders());
        $this->assertSame(['Foo' => ['Bar'], 'baZ' => ['Bam']], $r2->getHeaders());
        $this->assertSame('Bam', $r2->getHeaderLine('baz'));
        $this->assertSame(['Bam'], $r2->getHeader('baz'));
    }

    public function test_with_header_as_array()
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withHeader('baZ', ['Bam', 'Bar']);
        $this->assertSame(['Foo' => ['Bar']], $r->getHeaders());
        $this->assertSame(['Foo' => ['Bar'], 'baZ' => ['Bam', 'Bar']], $r2->getHeaders());
        $this->assertSame('Bam, Bar', $r2->getHeaderLine('baz'));
        $this->assertSame(['Bam', 'Bar'], $r2->getHeader('baz'));
    }

    public function test_with_header_replaces_different_case()
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withHeader('foO', 'Bam');
        $this->assertSame(['Foo' => ['Bar']], $r->getHeaders());
        $this->assertSame(['foO' => ['Bam']], $r2->getHeaders());
        $this->assertSame('Bam', $r2->getHeaderLine('foo'));
        $this->assertSame(['Bam'], $r2->getHeader('foo'));
    }

    public function test_with_added_header()
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withAddedHeader('foO', 'Baz');
        $this->assertSame(['Foo' => ['Bar']], $r->getHeaders());
        $this->assertSame(['Foo' => ['Bar', 'Baz']], $r2->getHeaders());
        $this->assertSame('Bar, Baz', $r2->getHeaderLine('foo'));
        $this->assertSame(['Bar', 'Baz'], $r2->getHeader('foo'));
    }

    public function test_with_added_header_as_array()
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withAddedHeader('foO', ['Baz', 'Bam']);
        $this->assertSame(['Foo' => ['Bar']], $r->getHeaders());
        $this->assertSame(['Foo' => ['Bar', 'Baz', 'Bam']], $r2->getHeaders());
        $this->assertSame('Bar, Baz, Bam', $r2->getHeaderLine('foo'));
        $this->assertSame(['Bar', 'Baz', 'Bam'], $r2->getHeader('foo'));
    }

    public function test_with_added_header_that_does_not_exist()
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withAddedHeader('nEw', 'Baz');
        $this->assertSame(['Foo' => ['Bar']], $r->getHeaders());
        $this->assertSame(['Foo' => ['Bar'], 'nEw' => ['Baz']], $r2->getHeaders());
        $this->assertSame('Baz', $r2->getHeaderLine('new'));
        $this->assertSame(['Baz'], $r2->getHeader('new'));
    }

    public function test_without_header_that_exists()
    {
        $r = new Response(200, ['Foo' => 'Bar', 'Baz' => 'Bam']);
        $r2 = $r->withoutHeader('foO');
        $this->assertTrue($r->hasHeader('foo'));
        $this->assertSame(['Foo' => ['Bar'], 'Baz' => ['Bam']], $r->getHeaders());
        $this->assertFalse($r2->hasHeader('foo'));
        $this->assertSame(['Baz' => ['Bam']], $r2->getHeaders());
    }

    public function test_without_header_that_does_not_exist()
    {
        $r = new Response(200, ['Baz' => 'Bam']);
        $r2 = $r->withoutHeader('foO');
        $this->assertSame($r, $r2);
        $this->assertFalse($r2->hasHeader('foo'));
        $this->assertSame(['Baz' => ['Bam']], $r2->getHeaders());
    }

    public function test_same_instance_when_removing_missing_header()
    {
        $r = new Response();
        $this->assertSame($r, $r->withoutHeader('foo'));
    }

    public static function trimmed_header_values()
    {
        return [
            [new Response(200, ['OWS' => " \t \tFoo\t \t "])],
            [(new Response())->withHeader('OWS', " \t \tFoo\t \t ")],
            [(new Response())->withAddedHeader('OWS', " \t \tFoo\t \t ")],
        ];
    }

    /**
     * @dataProvider trimmed_header_values
     */
    public function test_header_values_are_trimmed($r)
    {
        $this->assertSame(['OWS' => ['Foo']], $r->getHeaders());
        $this->assertSame('Foo', $r->getHeaderLine('OWS'));
        $this->assertSame(['Foo'], $r->getHeader('OWS'));
    }

    /**
     * @dataProvider invalid_with_header_provider
     */
    public function test_with_invalid_header($header, $header_value, $expected_message): void
    {
        $r = new Response();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expected_message);
        $r->withHeader($header, $header_value);
    }

    public function invalid_with_header_provider(): iterable
    {
        return [
            ['foo', [], 'Header values must be a string or an array of strings, empty array given'],
            ['foo', new \stdClass(),  'Header values must be RFC 7230 compatible strings'],
            [[], 'foo', 'Header name must be an RFC 7230 compatible string'],
            [false, 'foo', 'Header name must be an RFC 7230 compatible string'],
            [new \stdClass(), 'foo', 'Header name must be an RFC 7230 compatible string'],
            ['', 'foo', 'Header name must be an RFC 7230 compatible string'],
            ["Content-Type\r\n\r\n", 'foo', 'Header name must be an RFC 7230 compatible string'],
            ["Content-Type\r\n", 'foo', 'Header name must be an RFC 7230 compatible string'],
            ["Content-Type\n", 'foo', 'Header name must be an RFC 7230 compatible string'],
            ["\r\nContent-Type", 'foo', 'Header name must be an RFC 7230 compatible string'],
            ["\nContent-Type", 'foo', 'Header name must be an RFC 7230 compatible string'],
            ["\n", 'foo', 'Header name must be an RFC 7230 compatible string'],
            ["\r\n", 'foo', 'Header name must be an RFC 7230 compatible string'],
            ["\t", 'foo', 'Header name must be an RFC 7230 compatible string'],
        ];
    }
}
