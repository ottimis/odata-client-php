<?php

namespace SaintSystems\OData\Query;

interface IGrammar
{
    /**
     * Compile a select query into OData Uri
     *
     * @param Builder $query
     *
     * @return string
     */
    public function compileSelect(Builder $query): string;

    /**
     * Get the grammar specific operators.
     *
     * @return array
     */
    public function getOperators(): array;

    /**
     * Get the grammar specific functions.
     *
     * @return array
     */
    public function getFunctions(): array;

    /**
     * Get the grammar specific operators and functions.
     *
     * @return array
     */
    public function getOperatorsAndFunctions(): array;

    /**
     * Prepare the appropriate URI value for a where value.
     *
     * @param mixed $value
     *
     * @return string
     */
    public function prepareValue(mixed $value): string;

    /**
     * Get the appropriate query parameter place-holder for a value.
     *
     * @param mixed $value
     *
     * @return string
     */
    public function parameter(mixed $value): string;

    /**
     * Determine if the given value is a raw expression.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public function isExpression(mixed $value): bool;

    /**
     * Get the value of a raw expression.
     *
     * @param \Illuminate\Database\Query\Expression $expression
     *
     * @return string
     */
    public function getValue(\Illuminate\Database\Query\Expression $expression): string;

    /**
     * Convert an array of property names into a delimited string.
     *
     * @param array $properties
     *
     * @return string
     */
    public function columnize(array $properties): string;
}
