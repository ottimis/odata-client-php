<?php

namespace SaintSystems\OData\Query;

use SaintSystems\OData\IODataRequest;

class Processor implements IProcessor
{
    /**
     * @inheritdoc
     */
    public function processSelect(Builder $query, array|string $results): array|string
    {
        return $results;
    }
}
