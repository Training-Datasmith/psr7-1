<?php declare(strict_types=1);

namespace Tests\Nyholm\Psr7;

use Nyholm\Psr7\Request;
use Nyholm\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * @covers \Nyholm\Psr7\Request
 */
class Request_Test extends TestCase
{
    public function test_request_uri_may_be_string()
    {
        $r = new Request('GET', '/');
        $this->assertEquals('/', (string) $r->getUri());
    }

    public function test_request_uri_may_be_uri()
    {
        $uri = new Uri('/');
        $r = new Request('GET', $uri);
        $this->assertSame($uri, $r->getUri());
    }

    public function test_validate_request_uri()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to parse URI: "///"');

        new Request('GET', '///');
    }

    public function test_can_construct_with_body()
    {
        $r = new Request('GET', '/', [], 'baz');
        $this->assertInstanceOf(StreamInterface::class, $r->getBody());
        $this->assertEquals('baz', (string) $r->getBody());
    }

    public function test_null_body()
    {
        $r = new Request('GET', '/', [], null);
        $this->assertInstanceOf(StreamInterface::class, $r->getBody());
        $this->assertSame('', (string) $r->getBody());
    }

    public function test_falsey_body()
    {
        $r = new Request('GET', '/', [], '0');
        $this->assertInstanceOf(StreamInterface::class, $r->getBody());
        $this->assertSame('0', (string) $r->getBody());
    }

    public function test_constructor_does_not_read_stream_body()
    {
        $body = $this->getMockBuilder(StreamInterface::class)->getMock();
        $body->expects($this->never())
            ->method('__toString');

        $r = new Request('GET', '/', [], $body);
        $this->assertSame($body, $r->getBody());
    }

    public function test_with_uri()
    {
        $r1 = new Request('GET', '/');
        $u1 = $r1->getUri();
        $u2 = new Uri('http://www.example.com');
        $r2 = $r1->withUri($u2);
        $this->assertNotSame($r1, $r2);
        $this->assertSame($u2, $r2->getUri());
        $this->assertSame($u1, $r1->getUri());

        $r3 = new Request('GET', '/');
        $u3 = $r3->getUri();
        $r4 = $r3->withUri($u3);
        $this->assertSame($r3, $r4, 'If the Request did not change, then there is no need to create a new request object');

        $u4 = new Uri('/');
        $r5 = $r3->withUri($u4);
        $this->assertNotSame($r3, $r5);
    }

    public function test_same_instance_when_same_uri()
    {
        $r1 = new Request('GET', 'http://foo.com');
        $r2 = $r1->withUri($r1->getUri());
        $this->assertSame($r1, $r2);
    }

    public function test_with_request_target()
    {
        $r1 = new Request('GET', '/');
        $r2 = $r1->withRequestTarget('*');
        $this->assertEquals('*', $r2->getRequestTarget());
        $this->assertEquals('/', $r1->getRequestTarget());
    }

    public function test_with_invalid_request_target()
    {
        $r = new Request('GET', '/');
        $this->expectException(\InvalidArgumentException::class);
        $r->withRequestTarget('foo bar');
    }

    public function test_get_request_target()
    {
        $r = new Request('GET', 'https://nyholm.tech');
        $this->assertEquals('/', $r->getRequestTarget());

        $r = new Request('GET', 'https://nyholm.tech/foo?bar=baz');
        $this->assertEquals('/foo?bar=baz', $r->getRequestTarget());

        $r = new Request('GET', 'https://nyholm.tech?bar=baz');
        $this->assertEquals('/?bar=baz', $r->getRequestTarget());
    }

    public function test_request_target_does_not_allow_spaces()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid request target provided; cannot contain whitespace');

        $r1 = new Request('GET', '/');
        $r1->withRequestTarget('/foo bar');
    }

    public function test_request_target_defaults_to_slash()
    {
        $r1 = new Request('GET', '');
        $this->assertEquals('/', $r1->getRequestTarget());
        $r2 = new Request('GET', '*');
        $this->assertEquals('*', $r2->getRequestTarget());
        $r3 = new Request('GET', 'http://foo.com/bar baz/');
        $this->assertEquals('/bar%20baz/', $r3->getRequestTarget());
    }

    public function test_builds_request_target()
    {
        $r1 = new Request('GET', 'http://foo.com/baz?bar=bam');
        $this->assertEquals('/baz?bar=bam', $r1->getRequestTarget());
    }

    public function test_builds_request_target_with_falsey_query()
    {
        $r1 = new Request('GET', 'http://foo.com/baz?0');
        $this->assertEquals('/baz?0', $r1->getRequestTarget());
    }

    public function test_host_is_added_first()
    {
        $r = new Request('GET', 'http://foo.com/baz?bar=bam', ['Foo' => 'Bar']);
        $this->assertEquals([
            'Host' => ['foo.com'],
            'Foo' => ['Bar'],
        ], $r->getHeaders());
    }

    public function test_can_get_header_as_csv()
    {
        $r = new Request('GET', 'http://foo.com/baz?bar=bam', [
            'Foo' => ['a', 'b', 'c'],
        ]);
        $this->assertEquals('a, b, c', $r->getHeaderLine('Foo'));
        $this->assertEquals('', $r->getHeaderLine('Bar'));
    }

    public function test_host_is_not_overwritten_when_preserving_host()
    {
        $r = new Request('GET', 'http://foo.com/baz?bar=bam', ['Host' => 'a.com']);
        $this->assertEquals(['Host' => ['a.com']], $r->getHeaders());
        $r2 = $r->withUri(new Uri('http://www.foo.com/bar'), true);
        $this->assertEquals('a.com', $r2->getHeaderLine('Host'));
    }

    public function test_overrides_host_with_uri()
    {
        $r = new Request('GET', 'http://foo.com/baz?bar=bam');
        $this->assertEquals(['Host' => ['foo.com']], $r->getHeaders());
        $r2 = $r->withUri(new Uri('http://www.baz.com/bar'));
        $this->assertEquals('www.baz.com', $r2->getHeaderLine('Host'));
    }

    public function test_aggregates_headers()
    {
        $r = new Request('GET', '', [
            'ZOO' => 'zoobar',
            'zoo' => ['foobar', 'zoobar'],
        ]);
        $this->assertEquals(['ZOO' => ['zoobar', 'foobar', 'zoobar']], $r->getHeaders());
        $this->assertEquals('zoobar, foobar, zoobar', $r->getHeaderLine('zoo'));
    }

    public function test_support_numeric_headers()
    {
        $r = new Request('GET', '', [
            'Content-Length' => 200,
        ]);
        $this->assertSame(['Content-Length' => ['200']], $r->getHeaders());
        $this->assertSame('200', $r->getHeaderLine('Content-Length'));
    }

    public function test_support_numeric_header_names()
    {
        $r = new Request(
            'GET', '', [
                     '200' => 'NumericHeaderValue',
                     '0' => 'NumericHeaderValueZero',
                 ]
        );

        $this->assertSame(
            [
                '200' => ['NumericHeaderValue'],
                '0' => ['NumericHeaderValueZero'],
            ],
            $r->getHeaders()
        );

        $this->assertSame(['NumericHeaderValue'], $r->getHeader('200'));
        $this->assertSame('NumericHeaderValue', $r->getHeaderLine('200'));

        $this->assertSame(['NumericHeaderValueZero'], $r->getHeader('0'));
        $this->assertSame('NumericHeaderValueZero', $r->getHeaderLine('0'));

        $r = $r->withHeader('300', 'NumericHeaderValue2')
               ->withAddedHeader('200', ['A', 'B']);

        $this->assertSame(
            [
                '200' => ['NumericHeaderValue', 'A', 'B'],
                '0' => ['NumericHeaderValueZero'],
                '300' => ['NumericHeaderValue2'],
            ],
            $r->getHeaders()
        );

        $r = $r->withoutHeader('300');
        $this->assertSame(
            [
                '200' => ['NumericHeaderValue', 'A', 'B'],
                '0' => ['NumericHeaderValueZero'],
            ],
            $r->getHeaders()
        );
    }

    public function test_adds_port_to_header()
    {
        $r = new Request('GET', 'http://foo.com:8124/bar');
        $this->assertEquals('foo.com:8124', $r->getHeaderLine('host'));
    }

    public function test_adds_port_to_header_and_replace_previous_port()
    {
        $r = new Request('GET', 'http://foo.com:8124/bar');
        $r = $r->withUri(new Uri('http://foo.com:8125/bar'));
        $this->assertEquals('foo.com:8125', $r->getHeaderLine('host'));
    }

    public function test_cannot_have_header_with_empty_name()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Header name must be an RFC 7230 compatible string');
        $r = new Request('GET', 'https://example.com/');
        $r->withHeader('', 'Bar');
    }

    public function test_can_have_header_with_empty_value()
    {
        $r = new Request('GET', 'https://example.com/');
        $r = $r->withHeader('Foo', '');
        $this->assertEquals([''], $r->getHeader('Foo'));
    }

    public function test_update_host_from_uri()
    {
        $request = new Request('GET', '/');
        $request = $request->withUri(new Uri('https://nyholm.tech'));
        $this->assertEquals('nyholm.tech', $request->getHeaderLine('Host'));

        $request = new Request('GET', 'https://example.com/');
        $this->assertEquals('example.com', $request->getHeaderLine('Host'));
        $request = $request->withUri(new Uri('https://nyholm.tech'));
        $this->assertEquals('nyholm.tech', $request->getHeaderLine('Host'));

        $request = new Request('GET', '/');
        $request = $request->withUri(new Uri('https://nyholm.tech:8080'));
        $this->assertEquals('nyholm.tech:8080', $request->getHeaderLine('Host'));

        $request = new Request('GET', '/');
        $request = $request->withUri(new Uri('https://nyholm.tech:443'));
        $this->assertEquals('nyholm.tech', $request->getHeaderLine('Host'));
    }

    /**
     * @dataProvider provide_header_values_containing_not_allowed_chars
     */
    public function test_cannot_have_header_with_invalid_value(string $name)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Header name must be an RFC 7230 compatible string');
        $r = new Request('GET', 'https://example.com/');
        $r->withHeader($name, 'Bar');
    }

    public static function provide_header_values_containing_not_allowed_chars(): array
    {
        // Explicit tests for newlines as the most common exploit vector.
        $tests = [
            ["new\nline"],
            ["new\r\nline"],
            ["new\rline"],
            ["new\r\n line"],
            ["newline\n"],
            ["\nnewline"],
            ["newline\r\n"],
            ["\n\rnewline"],
        ];

        for ($i = 0; $i <= 0xFF; ++$i) {
            if ("\t" == \chr($i)) {
                continue;
            }
            if (' ' == \chr($i)) {
                continue;
            }
            if ($i >= 0x21 && $i <= 0x7E) {
                continue;
            }
            if ($i >= 0x80) {
                continue;
            }

            $tests[] = ['foo' . \chr($i) . 'bar'];
            $tests[] = ['foo' . \chr($i)];
        }

        return $tests;
    }
}
