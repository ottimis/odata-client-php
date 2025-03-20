<?php

namespace SaintSystems\OData;

use SaintSystems\OData\Query\IGrammar;
use SaintSystems\OData\Query\IProcessor;

interface IODataClient
{
    /**
     * Gets the IAuthenticationProvider for authenticating HTTP requests.
     * @var \SaintSystems\OData\IAuthenticationProvider
     */
    public function getAuthenticationProvider();

    /**
     * Set the odata.maxpagesize value of the request.
     *
     * @param int $pageSize
     *
     * @return IODataClient
     */
    public function setPageSize(int $pageSize): IODataClient;

    /**
     * Gets the page size
     *
     * @return ?int
     */
    public function getPageSize(): ?int;

    /**
     * Set the entityKey to be found.
     *
     * @param mixed $entityKey
     *
     * @return IODataClient
     */
    public function setEntityKey(mixed $entityKey): IODataClient;

    /**
     * Gets the entity key
     *
     * @return mixed
     */
    public function getEntityKey(): mixed;

    /**
     * Gets the base URL for requests of the client.
     * @return string
     */
    public function getBaseUrl(): string;

    /**
     * Gets the IHttpProvider for sending HTTP requests.
     * @return IHttpProvider
     */
    public function getHttpProvider(): IHttpProvider;

    /**
     * Begin a fluent query against an OData service
     *
     * @param string $entitySet
     *
     * @return \SaintSystems\OData\Query\Builder
     */
    public function from(string $entitySet): Query\Builder;

    /**
     * Begin a fluent query against an odata service
     *
     * @param array $properties
     *
     * @return \SaintSystems\OData\Query\Builder
     */
    public function select(array $properties = []): Query\Builder;

    /**
     * Get a new query builder instance.
     *
     * @return \SaintSystems\OData\Query\Builder
     */
    public function query(): Query\Builder;

    /**
     * Run a GET HTTP request against the service.
     *
     * @param $requestUri
     * @param array $bindings
     *
     * @return array|string
     */
    public function get($requestUri, array $bindings = []): array|string;

    /**
     * Run a GET HTTP request against the service.
     *
     * @param $requestUri
     * @param array $bindings
     *
     * @return IODataRequest|array
     */
    public function getNextPage($requestUri, array $bindings = []): IODataRequest|array;

    /**
     * Run a GET HTTP request against the service and return a generator
     *
     * @param $requestUri
     * @param array $bindings
     *
     * @return \Illuminate\Support\LazyCollection
     */
    public function cursor($requestUri, array $bindings = []): \Illuminate\Support\LazyCollection;

    /**
     * Get the query grammar used by the connection.
     *
     * @return IGrammar
     */
    public function getQueryGrammar(): IGrammar;

    /**
     * Set the query grammar used by the connection.
     *
     * @param IGrammar $grammar
     *
     * @return void
     */
    public function setQueryGrammar(IGrammar $grammar): void;

    /**
     * Get the query post processor used by the connection.
     *
     * @return IProcessor
     */
    public function getPostProcessor(): IProcessor;

    /**
     * Set the query post processor used by the connection.
     *
     * @param IProcessor $processor
     *
     * @return void
     */
    public function setPostProcessor(IProcessor $processor): void;
}
