<?php

namespace SaintSystems\OData;

class HttpRequestMessage
{
    /**
     * Gets or sets the body of the HTTP message.
     * @var mixed
     */
    public mixed $body;

    /**
     * Gets or sets whether this HTTP message returns a stream
     * @var bool
     */
    public bool $returnsStream = false;

    /**
     * Gets the collection of HTTP request headers.
     * @var array
     */
    public array $headers;

    /**
     * Gets or sets the HTTP method used by the HTTP request message.
     * @var string|HttpMethod
     */
    public string|HttpMethod $method;

    /**
     * Gets a set of properties for the HTTP request.
     * @var array
     */
    public array $properties;

    /**
     * Gets or sets the Uri used for the HTTP request.
     * @var string
     */
    public string $requestUri;

    /**
     * Gets or sets the HTTP message version.
     * @var string
     */
    public string $version;

    public function __construct($method = HttpMethod::GET, $requestUri = null)
    {
        $this->method = (string)$method;
        $this->requestUri = $requestUri;
        $this->headers = [];
        $this->returnsStream = false;
    }
}
