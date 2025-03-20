<?php

namespace SaintSystems\OData;

use Closure;
use SaintSystems\OData\Exception\ODataException;
use SaintSystems\OData\Query\Builder;
use SaintSystems\OData\Query\Grammar;
use SaintSystems\OData\Query\IGrammar;
use SaintSystems\OData\Query\IProcessor;
use SaintSystems\OData\Query\Processor;
use Illuminate\Support\LazyCollection;

class ODataClient implements IODataClient
{
    /**
     * The base service URL. For example, "https://services.odata.org/V4/TripPinService/"
     * @var string
     */
    private string $baseUrl;

    /**
     * The IAuthenticationProvider for authenticating request messages.
     * @var IAuthenticationProvider|Closure|null
     */
    private IAuthenticationProvider|Closure|null $authenticationProvider;

    /**
     * The IHttpProvider for sending HTTP requests.
     * @var IHttpProvider
     */
    private IHttpProvider $httpProvider;

    /**
     * The query grammar implementation.
     *
     * @var IGrammar
     */
    protected IGrammar $queryGrammar;

    /**
     * The query post processor implementation.
     *
     * @var IProcessor
     */
    protected IProcessor $postProcessor;

    /**
     * The return type for the entities
     *
     * @var ?string
     */
    private ?string $entityReturnType = null;

    /**
     * The page size
     *
     * @var ?int
     */
    private ?int $pageSize = null;

    /**
     * The entityKey to be found
     *
     * @var mixed
     */
    private mixed $entityKey = null;

    /**
     * Constructs a new ODataClient.
     * @param string $baseUrl The base service URL.
     * @param callable|null $authenticationProvider The IAuthenticationProvider for authenticating request messages.
     * @param IHttpProvider|null $httpProvider The IHttpProvider for sending requests.
     * @throws ODataException
     */
    public function __construct(
        string         $baseUrl,
        ?Callable      $authenticationProvider = null,
        ?IHttpProvider $httpProvider = null
    ) {
        $this->setBaseUrl($baseUrl);
        $this->authenticationProvider = $authenticationProvider;
        $this->httpProvider = $httpProvider ?: new GuzzleHttpProvider();

        // We need to initialize a query grammar and the query post processors
        // which are both very important parts of the OData abstractions
        // so we initialize these to their default values while starting.
        $this->useDefaultQueryGrammar();

        $this->useDefaultPostProcessor();
    }

    /**
     * Set the query grammar to the default implementation.
     *
     * @return void
     */
    public function useDefaultQueryGrammar(): void
    {
        $this->queryGrammar = $this->getDefaultQueryGrammar();
    }

    /**
     * Get the default query grammar instance.
     *
     * @return IGrammar
     */
    protected function getDefaultQueryGrammar(): IGrammar
    {
        return new Grammar;
    }

    /**
     * Set the query post processor to the default implementation.
     *
     * @return void
     */
    public function useDefaultPostProcessor(): void
    {
        $this->postProcessor = $this->getDefaultPostProcessor();
    }

    /**
     * Get the default post processor instance.
     *
     * @return IProcessor
     */
    protected function getDefaultPostProcessor(): IProcessor
    {
        return new Processor();
    }

    /**
     * Gets the IAuthenticationProvider for authenticating requests.
     *
     * @return Closure|IAuthenticationProvider|null
     */
    public function getAuthenticationProvider(): IAuthenticationProvider|Closure|null
    {
        return $this->authenticationProvider;
    }

    /**
     * Gets the base URL for requests of the client.
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Sets the base URL for requests of the client.
     * @param mixed $value
     *
     * @throws ODataException
     */
    public function setBaseUrl(mixed $value): void
    {
        if (empty($value)) {
            throw new ODataException(Constants::BASE_URL_MISSING);
        }

        $this->baseUrl = rtrim($value, '/') . '/';
    }

    /**
     * Gets the IHttpProvider for sending HTTP requests.
     *
     * @return IHttpProvider
     */
    public function getHttpProvider(): IHttpProvider
    {
        return $this->httpProvider;
    }

    /**
     * Begin a fluent query against an odata service
     *
     * @param string $entitySet
     *
     * @return Builder
     */
    public function from(string $entitySet): Builder
    {
        return $this->query()->from($entitySet);
    }

    /**
     * Begin a fluent query against an odata service
     *
     * @param array $properties
     *
     * @return Builder
     */
    public function select(array $properties = []): Builder
    {
        $properties = is_array($properties) ? $properties : func_get_args();

        return $this->query()->select($properties);
    }

    /**
     * Get a new query builder instance.
     *
     * @return Builder
     */
    public function query(): Builder
    {
        return new Builder(
            $this, $this->getQueryGrammar(), $this->getPostProcessor()
        );
    }

    /**
     * Run a GET HTTP request against the service.
     *
     * @param string $requestUri
     * @param array $bindings
     *
     * @return array|string
     */
    public function get($requestUri, array $bindings = []): array|string
    {
        list($response, $nextPage) = $this->getNextPage($requestUri, $bindings);
        return $response;
    }

    /**
     * Run a GET HTTP request against the service.
     *
     * @param string $requestUri
     * @param array $bindings
     *
     * @return array
     */
    public function getNextPage($requestUri, array $bindings = []): array
    {
        return $this->request(HttpMethod::GET, $requestUri, $bindings);
    }

    /**
     * Run a GET HTTP request against the service and return a generator.
     *
     * @param string $requestUri
     * @param array $bindings
     *
     * @return \Illuminate\Support\LazyCollection
     */
    public function cursor($requestUri, array $bindings = []): \Illuminate\Support\LazyCollection
    {
        return LazyCollection::make(function() use($requestUri, $bindings) {

            $nextPage = $requestUri;

            while (!is_null($nextPage)) {
                list($data, $nextPage) = $this->getNextPage($nextPage, $bindings);

                if (!is_null($nextPage)) {
                    $nextPage = str_replace($this->baseUrl, '', $nextPage);
                }

                yield from $data;
            }
        });
    }

    /**
     * Run a POST request against the service.
     *
     * @param string $requestUri
     * @param mixed  $postData
     *
     * @return array
     */
    public function post(string $requestUri, mixed $postData): array
    {
        return $this->request(HttpMethod::POST, $requestUri, $postData);
    }

    /**
     * Run a PATCH request against the service.
     *
     * @param string $requestUri
     * @param mixed  $body
     *
     * @return array
     */
    public function patch(string $requestUri, mixed $body): array
    {
        return $this->request(HttpMethod::PATCH, $requestUri, $body);
    }

    /**
     * Run a DELETE request against the service.
     *
     * @param string $requestUri
     *
     * @return array
     */
    public function delete(string $requestUri): array
    {
        return $this->request(HttpMethod::DELETE, $requestUri);
    }

    /**
     * Return an ODataRequest
     *
     * @param string $method
     * @param string $requestUri
     * @param mixed|null $body
     *
     * @return array
     *
     * @throws ODataException
     */
    public function request(string $method, string $requestUri, mixed $body = null): array
    {
        $request = new ODataRequest($method, $this->baseUrl.$requestUri, $this, $this->entityReturnType);

        if ($body) {
            $request->attachBody($body);
        }

        return $request->execute();
    }

    /**
     * Get the query grammar used by the connection.
     *
     * @return IGrammar
     */
    public function getQueryGrammar(): IGrammar
    {
        return $this->queryGrammar;
    }

    /**
     * Set the query grammar used by the connection.
     *
     * @param  IGrammar  $grammar
     *
     * @return void
     */
    public function setQueryGrammar(IGrammar $grammar): void
    {
        $this->queryGrammar = $grammar;
    }

    /**
     * Get the query post processor used by the connection.
     *
     * @return IProcessor
     */
    public function getPostProcessor(): IProcessor
    {
        return $this->postProcessor;
    }

    /**
     * Set the query post processor used by the connection.
     *
     * @param IProcessor $processor
     *
     * @return void
     */
    public function setPostProcessor(IProcessor $processor): void
    {
        $this->postProcessor = $processor;
    }

    /**
     * Set the entity return type
     *
     * @param string $entityReturnType
     */
    public function setEntityReturnType(string $entityReturnType): void
    {
        $this->entityReturnType = $entityReturnType;
    }

    /**
     * Set the odata.maxpagesize value of the request.
     *
     * @param int $pageSize
     *
     * @return IODataClient
     */
    public function setPageSize(int $pageSize): IODataClient
    {
        $this->pageSize = $pageSize;
        return $this;
    }

    /**
     * Gets the page size
     *
     * @return ?int
     */
    public function getPageSize(): ?int
    {
        return $this->pageSize;
    }

    /**
     * Set the entityKey to be found.
     *
     * @param mixed $entityKey
     *
     * @return IODataClient
     */
    public function setEntityKey(mixed $entityKey): IODataClient
    {
        $this->entityKey = $entityKey;
        return $this;
    }

    /**
     * Gets the entity key
     *
     * @return mixed
     */
    public function getEntityKey(): mixed
    {
        return $this->entityKey;
    }
}
