<?php

namespace SaintSystems\OData\Query;

use SaintSystems\OData\IODataRequest;

interface IProcessor
{
    /**
     * Process the results of a "select" query.
     *
     * @param Builder       $query
     * @param array|string $results
     *
     * @return array|string
     */
    public function processSelect(Builder $query, array|string $results): array|string;
}
