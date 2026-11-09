<?php

declare(strict_types=1);

namespace Nyholm\Psr7;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Trait implementing functionality common to requests and responses.
 *
 * @author Michael Dowling and contributors to guzzlehttp/psr7
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Martijn van der Ven <martijn@vanderven.se>
 *
 * @internal should not be used outside of Nyholm/Psr7 as it does not fall under our BC promise
 */
trait Message_Trait
{
    /** @var array Map of all registered headers, as original name => array of values */
    private $headers = [];

    /** @var array Map of lowercase header name => original name at registration */
    private $header_names = [];

    /** @var string */
    private $protocol = '1.1';

    /** @var StreamInterface|null */
    private $stream;

    public function getProtocolVersion(): string
    {
        return $this->protocol;
    }

    /**
     * @return static
     */
    public function withProtocolVersion($version): MessageInterface
    {
        if (!\is_scalar($version)) {
            throw new \InvalidArgumentException('Protocol version must be a string');
        }

        if ($this->protocol === $version) {
            return $this;
        }

        $new = clone $this;
        $new->protocol = (string) $version;

        return $new;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader($header): bool
    {
        return isset($this->header_names[\strtr($header, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')]);
    }

    public function getHeader($header): array
    {
        if (!\is_string($header)) {
            throw new \InvalidArgumentException('Header name must be an RFC 7230 compatible string');
        }

        $header = \strtr($header, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
        if (!isset($this->header_names[$header])) {
            return [];
        }

        $header = $this->header_names[$header];

        return $this->headers[$header];
    }

    public function getHeaderLine($header): string
    {
        return \implode(', ', $this->getHeader($header));
    }

    /**
     * @return static
     */
    public function withHeader($header, $value): MessageInterface
    {
        $value = $this->validate_and_trim_header($header, $value);
        $normalized = \strtr($header, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');

        $new = clone $this;
        if (isset($new->header_names[$normalized])) {
            unset($new->headers[$new->header_names[$normalized]]);
        }
        $new->header_names[$normalized] = $header;
        $new->headers[$header] = $value;

        return $new;
    }

    /**
     * @return static
     */
    public function withAddedHeader($header, $value): MessageInterface
    {
        if (!\is_string($header) || '' === $header) {
            throw new \InvalidArgumentException('Header name must be an RFC 7230 compatible string');
        }

        $new = clone $this;
        $new->set_headers([$header => $value]);

        return $new;
    }

    /**
     * @return static
     */
    public function withoutHeader($header): MessageInterface
    {
        if (!\is_string($header)) {
            throw new \InvalidArgumentException('Header name must be an RFC 7230 compatible string');
        }

        $normalized = \strtr($header, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
        if (!isset($this->header_names[$normalized])) {
            return $this;
        }

        $header = $this->header_names[$normalized];
        $new = clone $this;
        unset($new->headers[$header], $new->header_names[$normalized]);

        return $new;
    }

    public function getBody(): StreamInterface
    {
        if (null === $this->stream) {
            $this->stream = Stream::create('');
        }

        return $this->stream;
    }

    /**
     * @return static
     */
    public function withBody(StreamInterface $body): MessageInterface
    {
        if ($body === $this->stream) {
            return $this;
        }

        $new = clone $this;
        $new->stream = $body;

        return $new;
    }

    private function set_headers(array $headers): void
    {
        foreach ($headers as $header => $value) {
            if (\is_int($header)) {
                // If a header name was set to a numeric string, PHP will cast the key to an int.
                // We must cast it back to a string in order to comply with validation.
                $header = (string) $header;
            }
            $value = $this->validate_and_trim_header($header, $value);
            $normalized = \strtr($header, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
            if (isset($this->header_names[$normalized])) {
                $header = $this->header_names[$normalized];
                $this->headers[$header] = \array_merge($this->headers[$header], $value);
            } else {
                $this->header_names[$normalized] = $header;
                $this->headers[$header] = $value;
            }
        }
    }

    /**
     * Make sure the header complies with RFC 7230.
     *
     * Header names must be a non-empty string consisting of token characters.
     *
     * Header values must be strings consisting of visible characters with all optional
     * leading and trailing whitespace stripped. This method will always strip such
     * optional whitespace. Note that the method does not allow folding whitespace within
     * the values as this was deprecated for almost all instances by the RFC.
     *
     * header-field = field-name ":" OWS field-value OWS
     * field-name   = 1*( "!" / "#" / "$" / "%" / "&" / "'" / "*" / "+" / "-" / "." / "^"
     *              / "_" / "`" / "|" / "~" / %x30-39 / ( %x41-5A / %x61-7A ) )
     * OWS          = *( SP / HTAB )
     * field-value  = *( ( %x21-7E / %x80-FF ) [ 1*( SP / HTAB ) ( %x21-7E / %x80-FF ) ] )
     *
     * @see https://tools.ietf.org/html/rfc7230#section-3.2.4
     */
    private function validate_and_trim_header($header, $values): array
    {
        if (!\is_string($header) || 1 !== \preg_match("@^[!#$%&'*+.^_`|~0-9A-Za-z-]+$@D", $header)) {
            throw new \InvalidArgumentException('Header name must be an RFC 7230 compatible string');
        }

        if (!\is_array($values)) {
            // This is simple, just one value.
            if ((!\is_numeric($values) && !\is_string($values)) || 1 !== \preg_match("@^[ \t\x21-\x7E\x80-\xFF]*$@", (string) $values)) {
                throw new \InvalidArgumentException('Header values must be RFC 7230 compatible strings');
            }

            return [\trim((string) $values, " \t")];
        }

        if (empty($values)) {
            throw new \InvalidArgumentException('Header values must be a string or an array of strings, empty array given');
        }

        // Assert Non empty array
        $return_values = [];
        foreach ($values as $v) {
            if ((!\is_numeric($v) && !\is_string($v)) || 1 !== \preg_match("@^[ \t\x21-\x7E\x80-\xFF]*$@D", (string) $v)) {
                throw new \InvalidArgumentException('Header values must be RFC 7230 compatible strings');
            }

            $return_values[] = \trim((string) $v, " \t");
        }

        return $return_values;
    }
}
