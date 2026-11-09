<?php

declare(strict_types=1);

namespace Nyholm\Psr7;

use Psr\Http\Message\{ServerRequestInterface, StreamInterface, UploadedFileInterface, UriInterface};

/**
 * @author Michael Dowling and contributors to guzzlehttp/psr7
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Martijn van der Ven <martijn@vanderven.se>
 *
 * @final This class should never be extended. See https://github.com/Nyholm/psr7/blob/master/doc/final.md
 */
class Server_Request implements ServerRequestInterface
{
    use Message_Trait;
    use Request_Trait;

    /** @var array */
    private $attributes = [];

    /** @var array */
    private $cookie_params = [];

    /** @var array|object|null */
    private $parsed_body;

    /** @var array */
    private $query_params = [];

    /** @var array */
    private $server_params;

    /** @var UploadedFileInterface[] */
    private $uploaded_files = [];

    /**
     * @param string $method HTTP method
     * @param string|UriInterface $uri URI
     * @param array $headers Request headers
     * @param string|resource|StreamInterface|null $body Request body
     * @param string $version Protocol version
     * @param array $server_params Typically the $_SERVER superglobal
     */
    public function __construct(string $method, $uri, array $headers = [], $body = null, string $version = '1.1', array $server_params = [])
    {
        $this->server_params = $server_params;

        if (!($uri instanceof UriInterface)) {
            $uri = new Uri($uri);
        }

        $this->method = $method;
        $this->uri = $uri;
        $this->set_headers($headers);
        $this->protocol = $version;
        \parse_str($uri->getQuery(), $this->query_params);

        if (!$this->hasHeader('Host')) {
            $this->update_host_from_uri();
        }

        // If we got no body, defer initialization of the stream until Server_Request::getBody()
        if ('' !== $body && null !== $body) {
            $this->stream = Stream::create($body);
        }
    }

    public function getServerParams(): array
    {
        return $this->server_params;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploaded_files;
    }

    /**
     * @return static
     */
    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        $new = clone $this;
        $new->uploaded_files = $uploadedFiles;

        return $new;
    }

    public function getCookieParams(): array
    {
        return $this->cookie_params;
    }

    /**
     * @return static
     */
    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        $new = clone $this;
        $new->cookie_params = $cookies;

        return $new;
    }

    public function getQueryParams(): array
    {
        return $this->query_params;
    }

    /**
     * @return static
     */
    public function withQueryParams(array $query): ServerRequestInterface
    {
        $new = clone $this;
        $new->query_params = $query;

        return $new;
    }

    /**
     * @return array|object|null
     */
    public function getParsedBody()
    {
        return $this->parsed_body;
    }

    /**
     * @return static
     */
    public function withParsedBody($data): ServerRequestInterface
    {
        if (!\is_array($data) && !\is_object($data) && null !== $data) {
            throw new \InvalidArgumentException('First parameter to withParsedBody MUST be object, array or null');
        }

        $new = clone $this;
        $new->parsed_body = $data;

        return $new;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return mixed
     */
    public function getAttribute($attribute, $default = null)
    {
        if (!\is_string($attribute)) {
            throw new \InvalidArgumentException('Attribute name must be a string');
        }

        if (false === \array_key_exists($attribute, $this->attributes)) {
            return $default;
        }

        return $this->attributes[$attribute];
    }

    /**
     * @return static
     */
    public function withAttribute($attribute, $value): ServerRequestInterface
    {
        if (!\is_string($attribute)) {
            throw new \InvalidArgumentException('Attribute name must be a string');
        }

        $new = clone $this;
        $new->attributes[$attribute] = $value;

        return $new;
    }

    /**
     * @return static
     */
    public function withoutAttribute($attribute): ServerRequestInterface
    {
        if (!\is_string($attribute)) {
            throw new \InvalidArgumentException('Attribute name must be a string');
        }

        if (false === \array_key_exists($attribute, $this->attributes)) {
            return $this;
        }

        $new = clone $this;
        unset($new->attributes[$attribute]);

        return $new;
    }
}
