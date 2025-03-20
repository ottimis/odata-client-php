<?php

namespace SaintSystems\OData;

class Uri
{
    const URI_PARTS = [
        'scheme',
        'host',
        'port',
        'user',
        'pass',
        'path',
        'query',
        'fragment'
    ];

    public string $scheme;

    public string $host;

    public int $port;

    public string $user;

    public string $pass;

    public string $path;

    public string $query;

    public string $fragment;

    private array|false|int|null|string $parsed;

    /**
     * @param string|null $uri
     */
    public function __construct(?string $uri = null)
    {
        if ($uri == null) return;
        $uriParsed = parse_url($uri);
        $this->parsed = $uriParsed;
        foreach(self::URI_PARTS as $uriPart) {
            if (isset($uriParsed[$uriPart])) {
                $this->$uriPart = $uriParsed[$uriPart];
            }
        }
    }

    public function __toString()
    {
        return http_build_url($this->parsed);
    }
}
