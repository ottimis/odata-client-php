<?php

namespace SaintSystems\OData;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class GuzzleHttpProvider implements IHttpProvider
{
    /**
    * The Guzzle client used to make the HTTP request
    *
    * @var Client
    */
    protected Client $http;

    /**
    * The timeout, in seconds
    *
    * @var string
    */
    protected string $timeout;

    protected array $extra_options;

    /**
     * Creates a new HttpProvider
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->http = new Client($config);
        $this->timeout = 0;
        $this->extra_options = array();
    }

    /**
     * Gets the timeout limit of the cURL request
     * @return integer  The timeout in ms
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Sets the timeout limit of the cURL request
     *
     * @param integer $timeout The timeout in ms
     *
     * @return $this
     */
    public function setTimeout($timeout): static
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Configures the default options for the client.
     *
     * @param array $config
     */
    public function configureDefaults($config): void
    {
        $this->http->configureDefaults($config);
    }

    public function setExtraOptions($options): void
    {
        $this->extra_options = $options;
    }

    /**
     * Executes the HTTP request using Guzzle
     *
     * @param HttpRequestMessage $request
     *
     * @return ResponseInterface object or array of objects
     *         of class $returnType
     * @throws GuzzleException
     */
    public function send(HttpRequestMessage $request): ResponseInterface
    {
        $options = [
            'headers' => $request->headers,
            'stream' =>  $request->returnsStream,
            'timeout' => $this->timeout
        ];

        foreach ($this->extra_options as $key => $value)
        {
            $options[$key] = $value;
        }

        if ($request->method == HttpMethod::POST || $request->method == HttpMethod::PUT || $request->method == HttpMethod::PATCH) {
            $options['body'] = $request->body;
        }

        $result = $this->http->request(
            $request->method,
            $request->requestUri,
            $options
        );

        return $result;
    }
}
