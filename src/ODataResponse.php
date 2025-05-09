<?php

/**
 * Copyright (c) Saint Systems, LLC.  All Rights Reserved.
 * Licensed under the MIT License.  See License in the project root
 * for license information.
 *
 * ODataResponse File
 * PHP version 7
 *
 * @category  Library
 * @package   SaintSystems.OData
 * @copyright 2017 Saint Systems, LLC
 * @license   https://opensource.org/licenses/MIT MIT License
 * @version   GIT: 0.1.0
 * @link      https://www.microsoft.com/en-us/OData365/
 */

namespace SaintSystems\OData;

/**
 * Class ODataResponse
 *
 * @category Library
 * @package  SaintSystems.OData
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class ODataResponse
{
    /**
     * The request
     *
     * @var object
     */
    public object $request;

    /**
     * The body of the response
     *
     * @var ?string
     */
    private ?string $body;

    /**
     * The body of the response,
     * decoded into an array
     *
     * @var array(string)
     */
    private array $decodedBody;

    /**
     * The headers of the response
     *
     * @var array(string)
     */
    private array $headers;

    /**
     * The status code of the response
     *
     * @var ?string
     */
    private ?string $httpStatusCode;

    /**
     * Creates a new OData HTTP response entity
     *
     * @param object $request        The request
     * @param string|null $body           The body of the response
     * @param string|null $httpStatusCode The returned status code
     * @param array $headers        The returned headers
     */
    public function __construct(object $request, ?string $body = null, ?string $httpStatusCode = null, array $headers = array())
    {
        $this->request = $request;
        $this->body = $body;
        $this->httpStatusCode = $httpStatusCode;
        $this->headers = $headers;
        $this->decodedBody = $this->body ? $this->decodeBody() : [];
    }

    /**
     * Decode the JSON response into an array
     *
     * @return array The decoded response
     */
    private function decodeBody(): array
    {
        $decodedBody = json_decode($this->body, true);
        if ($decodedBody === null) {
            $matches = null;
            preg_match('~\{(?:[^{}]|(?R))*\}~', $this->body, $matches);
            $decodedBody = json_decode($matches[0], true);
            if ($decodedBody === null) {
                $decodedBody = array();
            }
        }
        return $decodedBody;
    }

    /**
     * Get the decoded body of the HTTP response
     *
     * @return array The decoded body
     */
    public function getBody(): array
    {
        return $this->decodedBody;
    }

    /**
     * Get the undecoded body of the HTTP response
     *
     * @return ?string The undecoded body
     */
    public function getRawBody(): ?string
    {
        return $this->body;
    }

    /**
     * Get the status of the HTTP response
     *
     * @return ?string The HTTP status
     */
    public function getStatus(): ?string
    {
        return $this->httpStatusCode;
    }

    /**
     * Get the headers of the response
     *
     * @return array The response headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Converts the response JSON object to a OData SDK object
     *
     * @param mixed $returnType The type to convert the object(s) to
     *
     * @return mixed object or array of objects of type $returnType
     */
    public function getResponseAsObject(mixed $returnType): mixed
    {
        $class = $returnType;
        $result = $this->getBody();

        //If more than one object is returned
        if (array_key_exists(Constants::ODATA_VALUE, $result)) {
            $objArray = array();
            foreach ($result[Constants::ODATA_VALUE] as $obj) {
                $objArray[] = new $class($obj);
            }
            return $objArray;
        } else {
            return [new $class($result)];
        }
    }

    /**
     * Gets the @odata.nextLink of a response object from OData
     *
     * @return string|null next link, if provided
     */
    public function getNextLink(): ?string
    {
        if (array_key_exists(Constants::ODATA_NEXT_LINK, $this->getBody())) {
            $nextLink = $this->getBody()[Constants::ODATA_NEXT_LINK];
            return $nextLink;
        }
        return null;
    }

    /**
     * Gets the skip token of a response object from OData
     *
     * @return ?string skip token, if provided
     */
    public function getSkipToken(): ?string
    {
        $nextLink = $this->getNextLink();
        if (is_null($nextLink)) {
            return null;
        };
        $url = explode("?", $nextLink)[1];
        $url = explode("skiptoken=", $url);
        if (count($url) > 1) {
            return $url[1];
        }
        return null;
    }

    /**
     * Gets the Id of response object (if set) from OData
     *
     * @return mixed id if this was an insert, if provided
     */
    public function getId(): mixed
    {
        if (array_key_exists(Constants::ODATA_ID, $this->getHeaders())) {
            $id = $this->getBody()[Constants::ODATA_ID];
            return $id;
        }
        return null;
    }
}
