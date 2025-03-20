<?php

namespace SaintSystems\OData\Query;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use SaintSystems\OData\Constants;
use SaintSystems\OData\Exception\ODataQueryException;
use SaintSystems\OData\IODataClient;
use SaintSystems\OData\IODataRequest;
use SaintSystems\OData\QueryOptions;

class Builder
{
    /**
     * Gets the IODataClient for handling requests.
     * @var IODataClient
     */
    public IODataClient $client;

    /**
     * Gets the URL for the built request, without query string.
     * @var string
     */
    public string $requestUrl;

    /**
     * Gets the URL for the built request, without query string.
     * @var object
     */
    public object $returnType;

    /**
     * The current query value bindings.
     *
     * @var array
     */
    public array $bindings = [
        'select' => [],
        'where'  => [],
        'order'  => [],
    ];

    /**
     * The entity set which the query is targeting.
     *
     * @var string
     */
    public string $entitySet;

    /**
     * The entity key of the entity set which the query is targeting.
     *
     * @var string|int|array|null
     */
    public string|int|array|null $entityKey = null;

    /**
     * The placeholder property for the ? operator in the OData querystring
     *
     * @var string
     */
    public string $queryString = '?';

    /**
     * An aggregate function to be run.
     *
     * @var ?boolean
     */
    public ?bool $count = null;

    /**
     * Whether to include a total count of items matching
     * the request be returned along with the result
     *
     * @var boolean
     */
    public bool $totalCount;

    /**
     * The specific set of properties to return for this entity or complex type
     * http://docs.oasis-open.org/odata/odata/v4.0/errata03/os/complete/part2-url-conventions/odata-v4.0-errata03-os-part2-url-conventions-complete.html#_Toc453752360
     *
     * @var ?array
     */
    public ?array $properties = null;

    /**
     * The where constraints for the query.
     *
     * @var array
     */
    public array $wheres;

    /**
     * The groupings for the query.
     *
     * @var array
     */
    public array $groups;

    /**
     * The orderings for the query.
     *
     * @var array
     */
    public array $orders;

    /**
     * The maximum number of records to return.
     *
     * @var int
     */
    public int $take;

    /**
     * The desired page size.
     *
     * @var int
     */
    public int $pageSize;

    /**
     * The number of records to skip.
     *
     * @var int
     */
    public int $skip;

    /**
     * The skiptoken.
     *
     * @var int
     */
    public int $skiptoken;

    /**
     * All of the available clause operators.
     *
     * @var array
     */
    public array $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'like', 'like binary', 'not like', 'between', 'ilike',
        '&', '|', '^', '<<', '>>',
        'rlike', 'regexp', 'not regexp',
        '~', '~*', '!~', '!~*', 'similar to',
        'not similar to', 'not ilike', '~~*', '!~~*',
    ];

    /**
     * @var array
     */
    public array $select = [];

    /**
     * @var array
     */
    public array $expands;

    /**
     * @var IProcessor
     */
    private IProcessor $processor;

    /**
     * @var IGrammar
     */
    private IGrammar $grammar;

    /**
     * Create a new query builder instance.
     *
     * @param IODataClient $client
     * @param IGrammar|null $grammar
     * @param IProcessor|null $processor
     */
    public function __construct(
        IODataClient $client,
        ?IGrammar $grammar = null,
        ?IProcessor $processor = null
    ) {
        $this->client = $client;
        $this->grammar = $grammar ?: $client->getQueryGrammar();
        $this->processor = $processor ?: $client->getPostProcessor();
    }

    /**
     * Set the properties to be selected.
     *
     * @param  array|mixed  $properties
     *
     * @return $this
     */
    public function select(mixed $properties = []): static
    {
        $this->properties = is_array($properties) ? $properties : func_get_args();

        return $this;
    }

    /**
     * Add a new properties to the $select query option.
     *
     * @param array|mixed $select
     *
     * @return $this
     */
    public function addSelect(mixed $select): static
    {
        $select = is_array($select) ? $select : func_get_args();

        $this->select = array_merge((array) $this->select, $select);

        return $this;
    }

    /**
     * Set the entity set which the query is targeting.
     *
     * @param  string  $entitySet
     *
     * @return $this
     */
    public function from(string $entitySet): static
    {
        $this->entitySet = $entitySet;

        return $this;
    }

    /**
     * Filter the entity set on the primary key.
     *
     * @param string|array|int $id
     *
     * @return $this
     */
    public function whereKey(string|array|int $id): static
    {
        $this->entityKey = $id;
        $this->client->setEntityKey($this->entityKey);
        return $this;
    }

    /**
     * Add an $expand clause to the query.
     *
     * @param array|string $properties
     * @return $this
     */
    public function expand(array|string $properties = []): static
    {
        $this->expands = is_array($properties) ? $properties : func_get_args();

        return $this;
    }

    /*
     * TODO: do we still need this? lots of bugs in here!!!
     *
    public function expand($property, $first, $operator = null, $second = null, $type = 'inner', $ref = false, $count = false)
    {
        //TODO: need to flush out this method as it will work much like the where and join methods
        $expand = new ExpandClause($this, $type, $property);

        // If the first "column" of the join is really a Closure instance the developer
        // is trying to build a join with a complex "on" clause containing more than
        // one condition, so we'll add the join and call a Closure with the query.
        if ($first instanceof Closure) {
            call_user_func($first, $expand);

            $this->expands[] = $expand;

            $this->addBinding($expand->getBindings(), 'expand');
        }

        // If the column is simply a string, we can assume the join simply has a basic
        // "expand" clause with a single condition. So we will just build the expand with
        // this simple expand clauses attached to it. There is not an expand callback.
        else {
            $method = $where ? 'where' : 'on';

            $this->expands[] = $expand->$method($first, $operator, $second);

            $this->addBinding($expand->getBindings(), 'expand');
        }

        return $this;
    }
    */

    /**
     * Apply the callback's query changes if the given "value" is true.
     *
     * @param bool $value
     * @param \Closure $callback
     * @param \Closure|null $default
     *
     * @return Builder
     */
    public function when(bool $value, Closure $callback, ?Closure $default = null): Builder|static
    {
        $builder = $this;

        if ($value) {
            $builder = call_user_func($callback, $builder);
        } elseif ($default) {
            $builder = call_user_func($default, $builder);
        }

        return $builder;
    }

    /**
     * Set the properties to be ordered.
     *
     * @param mixed|array $properties
     *
     * @return $this
     */
    public function order(mixed $properties = []): static
    {
        $order = is_array($properties) && count(func_get_args()) === 1 ? $properties : func_get_args();

        if (!(isset($order[0]) && is_array($order[0]))) {
            $order = array($order);
        }

        $this->orders = $this->buildOrders($order);

        return $this;
    }

    /**
     * Set the sql property to be ordered.
     *
     * @param string $sql
     *
     * @return $this
     */
    public function orderBySQL(string $sql = ''): static
    {
        $this->orders = array(['sql' => $sql]);

        return $this;
    }

    /**
     * Reformat array to match grammar structure
     *
     * @param array $orders
     *
     * @return array
     */
    private function buildOrders(array $orders = []): array
    {
        $_orders = [];

        foreach ($orders as &$order) {
            $column = $order['column'] ?? $order[0];
            $direction = $order['direction'] ?? ($order[1] ?? 'asc');

            array_push($_orders, [
                'column' => $column,
                'direction' => $direction
            ]);
        }

        return $_orders;
    }

    /**
     * Merge an array of where clauses and bindings.
     *
     * @param array $wheres
     * @param array $bindings
     * @return void
     */
    public function mergeWheres(array $wheres, array $bindings): void
    {
        $this->wheres = array_merge((array) $this->wheres, (array) $wheres);

        $this->bindings['where'] = array_values(
            array_merge($this->bindings['where'], (array) $bindings)
        );
    }

    /**
     * Add a basic where ($filter) clause to the query.
     *
     * @param array|string|\Closure $column
     * @param string|int|null $operator
     * @param mixed $value
     * @param string|null $boolean
     *
     * @return $this
     */
    public function where(array|string|Closure $column, string|int|null $operator = null, mixed $value = null, ?string $boolean = 'and'): Builder|static
    {
        // If the column is an array, we will assume it is an array of key-value pairs
        // and can add them each as a where clause. We will maintain the boolean we
        // received when the method was called and pass it into the nested where.
        if (is_array($column)) {
            return $this->addArrayOfWheres($column, $boolean);
        }

        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        list($value, $operator) = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() == 2
        );

        // If the columns is actually a Closure instance, we will assume the developer
        // wants to begin a nested where statement which is wrapped in parenthesis.
        // We'll add that Closure to the query then return back out immediately.
        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            list($value, $operator) = [$operator, '='];
        }

        // If the value is a Closure, it means the developer is performing an entire
        // sub-select within the query and we will need to compile the sub-select
        // within the where clause to get the appropriate query record results.
        if ($value instanceof Closure) {
            return $this->whereSub($column, $operator, $value, $boolean);
        }

        // If the value is "null", we will just assume the developer wants to add a
        // where null clause to the query. So, we will allow a short-cut here to
        // that method for convenience so the developer doesn't have to check.
        if (is_null($value)) {
            return $this->whereNull($column, $boolean, $operator != '=');
        }

        // If the column is making a JSON reference we'll check to see if the value
        // is a boolean. If it is, we'll add the raw boolean string as an actual
        // value to the query to ensure this is properly handled by the query.
        // if (Str::contains($column, '->') && is_bool($value)) {
        //     $value = new Expression($value ? 'true' : 'false');
        // }

        // Now that we are working with just a simple query we can put the elements
        // in our array and add the query binding to our array of bindings that
        // will be bound to each SQL statements when it is finally executed.
        $type = 'Basic';
        if($this->isOperatorAFunction($operator)){
            $type = 'Function';
        }

        $this->wheres[] = compact(
            'type', 'column', 'operator', 'value', 'boolean'
        );

        if (! $value instanceof Expression) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }

    /**
     * Add an array of where clauses to the query.
     *
     * @param array $column
     * @param string $boolean
     * @param string $method
     *
     * @return $this
     */
    protected function addArrayOfWheres(array $column, string $boolean, string $method = 'where'): Builder|static
    {
        return $this->whereNested(function ($query) use ($column, $method) {
            foreach ($column as $key => $value) {
                if (is_numeric($key) && is_array($value)) {
                    $query->{$method}(...array_values($value));
                } else {
                    $query->$method($key, '=', $value);
                }
            }
        }, $boolean);
    }

    /**
     * Determine if the given operator is actually a function.
     *
     * @param string $operator
     * @return bool
     */
    protected function isOperatorAFunction(string $operator): bool
    {
        return in_array(strtolower($operator), $this->grammar->getFunctions(), true);
    }

    /**
     * Prepare the value and operator for a where clause.
     *
     * @param ?string $value
     * @param string|int|null $operator
     * @param bool $useDefault
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function prepareValueAndOperator(?string $value, string|int|null $operator, bool $useDefault = false): array
    {
        if ($useDefault) {
            return [$operator, '='];
        } elseif ($this->invalidOperatorAndValue($operator, $value)) {
            throw new \InvalidArgumentException('Illegal operator and value combination.');
        }

        return [$value, $operator];
    }

    /**
     * Determine if the given operator and value combination is legal.
     *
     * Prevents using Null values with invalid operators.
     *
     * @param ?string $operator
     * @param mixed  $value
     *
     * @return bool
     */
    protected function invalidOperatorAndValue(?string $operator, mixed $value): bool
    {
        return is_null($value) && in_array($operator, $this->operators) &&
             ! in_array($operator, ['=', '<>', '!=']);
    }

    /**
     * Determine if the given operator is supported.
     *
     * @param ?string $operator
     * @return bool
     */
    protected function invalidOperator(?string $operator): bool
    {
        return ! in_array(strtolower($operator), $this->operators, true) &&
               ! in_array(strtolower($operator), $this->grammar->getOperatorsAndFunctions(), true);
    }

    /**
     * Add an "or where" clause to the query.
     *
     * @param \Closure|string|array $column
     * @param string|null $operator
     * @param mixed|null $value
     *
     * @return Builder|static
     */
    public function orWhere(Closure|string|array $column, ?string $operator = null, mixed $value = null): Builder|static
    {
        return $this->where($column, $operator, $value, 'or');
    }

    public function whereRaw($rawString, $boolean = 'and'): static
    {
        // We will add this where clause into this array of clauses that we
        // are building for the query. All of them will be compiled via a grammar
        // once the query is about to be executed and run against the database.
        $type = 'Raw';

        $this->wheres[] = compact(
            'type', 'rawString', 'boolean'
        );

        return $this;
    }

    public function orWhereRaw($rawString): static
    {
        return $this->whereRaw($rawString, 'or');
    }

    /**
     * Add a "where" clause comparing two columns to the query.
     *
     * @param array|string $first
     * @param string|null $operator
     * @param string|null $second
     * @param string $boolean
     * @return $this
     */
    public function whereColumn(array|string $first, ?string $operator = null, ?string $second = null, string $boolean = 'and'): Builder|static
    {
        // If the column is an array, we will assume it is an array of key-value pairs
        // and can add them each as a where clause. We will maintain the boolean we
        // received when the method was called and pass it into the nested where.
        if (is_array($first)) {
            return $this->addArrayOfWheres($first, $boolean, 'whereColumn');
        }

        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        list($second, $operator) = $this->prepareValueAndOperator(
            $second, $operator, func_num_args() == 2
        );

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            [$second, $operator] = [$operator, '='];
        }

        // Finally, we will add this where clause into this array of clauses that we
        // are building for the query. All of them will be compiled via a grammar
        // once the query is about to be executed and run against the database.
        $type = 'Column';

        $this->wheres[] = compact(
            'type', 'first', 'operator', 'second', 'boolean'
        );

        return $this;
    }

    /**
     * Add an "or where" clause comparing two columns to the query.
     *
     * @param array|string $first
     * @param string|null $operator
     * @param string|null $second
     * @return $this
     */
    public function orWhereColumn(array|string $first, ?string $operator = null, ?string $second = null): Builder|static
    {
        return $this->whereColumn($first, $operator, $second, 'or');
    }

    /**
     * Add a nested where statement to the query.
     *
     * @param \Closure $callback
     * @param string $boolean
     *
     * @return Builder|static
     */
    public function whereNested(Closure $callback, string $boolean = 'and'): Builder|static
    {
        call_user_func($callback, $query = $this->forNestedWhere());

        return $this->addNestedWhereQuery($query, $boolean);
    }

    /**
     * Create a new query instance for nested where condition.
     *
     * @return Builder
     */
    public function forNestedWhere(): Builder
    {
        return $this->newQuery()->from($this->entitySet);
    }

    /**
     * Add another query builder as a nested where to the query builder.
     *
     * @param Builder|static $query
     * @param string $boolean
     *
     * @return $this
     */
    public function addNestedWhereQuery(Builder $query, string $boolean = 'and'): static
    {
        if (count($query->wheres)) {
            $type = 'Nested';

            $this->wheres[] = compact('type', 'query', 'boolean');

            $this->addBinding($query->getBindings(), 'where');
        }

        return $this;
    }

    /**
     * Add a full sub-select to the query.
     *
     * @param string $column
     * @param string $operator
     * @param \Closure $callback
     * @param string $boolean
     *
     * @return $this
     */
    protected function whereSub(string $column, string $operator, Closure $callback, string $boolean): static
    {
        $type = 'Sub';

        // Once we have the query instance we can simply execute it so it can add all
        // of the sub-select's conditions to itself, and then we can cache it off
        // in the array of where clauses for the "main" parent query instance.
        call_user_func($callback, $query = $this->newQuery());

        $this->wheres[] = compact(
            'type', 'column', 'operator', 'query', 'boolean'
        );

        $this->addBinding($query->getBindings(), 'where');

        return $this;
    }

    /**
     * Add a "where null" clause to the query.
     *
     * @param string $column
     * @param string $boolean
     * @param bool $not
     * @return $this
     */
    public function whereNull(string $column, string $boolean = 'and', bool $not = false): static
    {
        $type = $not ? 'NotNull' : 'Null';

        $this->wheres[] = compact('type', 'column', 'boolean');

        return $this;
    }

    /**
     * Add an "or where null" clause to the query.
     *
     * @param string $column
     * @return Builder|static
     */
    public function orWhereNull(string $column): Builder|static
    {
        return $this->whereNull($column, 'or');
    }

    /**
     * Add a "where not null" clause to the query.
     *
     * @param string $column
     * @param string $boolean
     * @return Builder|static
     */
    public function whereNotNull(string $column, string $boolean = 'and'): Builder|static
    {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * Add an "or where not null" clause to the query.
     *
     * @param string $column
     * @return Builder|static
     */
    public function orWhereNotNull(string $column): Builder|static
    {
        return $this->whereNotNull($column, 'or');
    }




    /**
     * Add a "where in" clause to the query.
     *
     * @param string $column
     * @param array $list
     * @param string $boolean
     * @param bool $not
     * @return $this
     */
    public function whereIn(string $column, array $list, string $boolean = 'and', bool $not = false): static
    {
        $type = $not ? 'NotIn' : 'In';

        $this->wheres[] = compact('type', 'column', 'list', 'boolean');

        return $this;
    }

    /**
     * Add an "or where in" clause to the query.
     *
     * @param string $column
     * @param array $list
     * @return Builder|static
     */
    public function orWhereIn(string $column, array $list): Builder|static
    {
        return $this->whereIn($column, $list, 'or');
    }

    /**
     * Add a "where not in" clause to the query.
     *
     * @param string $column
     * @param array $list
     * @param string $boolean
     * @return Builder|static
     */
    public function whereNotIn(string $column, array $list, string $boolean = 'and'): Builder|static
    {
        return $this->whereIn($column, $list, $boolean, true);
    }

    /**
     * Add an "or where not in" clause to the query.
     *
     * @param string $column
     * @param array $list
     * @return Builder|static
     */
    public function orWhereNotIn(string $column, array $list): Builder|static
    {
        return $this->whereNotIn($column, $list, 'or');
    }



    /**
     * Get the HTTP Request representation of the query.
     *
     * @return string
     */
    public function toRequest(): string
    {
        return $this->grammar->compileSelect($this);
    }

    /**
     * Execute a query for a single record by ID. Single and multi-part IDs are supported.
     *
     * @param int|array|string $id the value of the ID or an associative array in case of multi-part IDs
     * @param array $properties
     *
     * @return mixed
     *
     * @throws ODataQueryException
     */
    public function find(int|array|string $id, array $properties = []): mixed
    {
        if (!isset($this->entitySet)) {
            throw new ODataQueryException(Constants::ENTITY_SET_REQUIRED);
        }
        return $this->whereKey($id)->first($properties);
    }

    /**
     * Get a single property's value from the first result of a query.
     *
     * @param string $property
     *
     * @return mixed
     */
    public function value(string $property): mixed
    {
        $result = (array) $this->first([$property]);

        return count($result) > 0 ? reset($result) : null;
    }

    /**
     * Execute the query and get the first result.
     *
     * @param array $properties
     *
     * @return mixed
     */
    public function first(array $properties = []): mixed
    {
        return $this->take(1)->get($properties)->first();
        //return $this->take(1)->get($properties);
    }

    /**
     * Set the "$skip" value of the query.
     *
     * @param int $value
     *
     * @return Builder|static
     */
    public function skip(int $value): Builder|static
    {
        $this->skip = $value;
        return $this;
    }

    /**
     * Set the "$skiptoken" value of the query.
     *
     * @param int $value
     *
     * @return Builder|static
     */
    public function skipToken(int $value): Builder|static
    {
        $this->skiptoken = $value;
        return $this;
    }

    /**
     * Set the "$top" value of the query.
     *
     * @param int $value
     *
     * @return Builder|static
     */
    public function take(int $value): Builder|static
    {
        $this->take = $value;
        return $this;
    }

    /**
     * Set the desired pagesize of the query;
     *
     * @param int $value
     *
     * @return Builder|static
     */
    public function pageSize(int $value): Builder|static
    {
        $this->pageSize = $value;
        $this->client->setPageSize($this->pageSize);
        return $this;
    }

    /**
     * Execute the query as a "GET" request.
     *
     * @param array $properties
     * @param array|null $options
     *
     * @return Collection
     */
    public function get(array $properties = [], ?array $options = null): Collection
    {
        if (is_numeric($properties)) {
            $options = $properties;
            $properties = [];
        }

        if (isset($options)) {
            $include_count = $options & QueryOptions::INCLUDE_COUNT;

            if ($include_count) {
                $this->totalCount = true;
            }
        }

        $original = $this->properties;

        if (is_null($original)) {
            $this->properties = $properties;
        }

        $results = $this->processor->processSelect($this, $this->runGet());

        $this->properties = $original;

        return collect($results);
        //return $results;
    }

    /**
     * Execute the query as a "POST" request.
     *
     * @param array $body
     * @param array $properties
     * @param array|null $options
     *
     * @return Collection
     */
    public function post(array $body = [], array $properties = [], ?array $options = null): Collection
    {
        if (is_numeric($properties)) {
            $options = $properties;
            $properties = [];
        }

        if (isset($options)) {
            $include_count = $options & QueryOptions::INCLUDE_COUNT;

            if ($include_count) {
                $this->totalCount = true;
            }
        }

        $original = $this->properties;

        if (is_null($original)) {
            $this->properties = $properties;
        }

        $results = $this->processor->processSelect($this, $this->runPost($body));

        $this->properties = $original;

        return collect($results);
    }

    /**
     * Execute the query as a "DELETE" request.
     *
     * @return boolean
     */
    public function delete($options = null): bool
    {
        $results = $this->processor->processSelect($this, $this->runDelete());

        return true;
    }

    /**
     * Execute the query as a "PATCH" request.
     *
     * @param array $properties
     * @param array|null $options
     *
     * @return Collection
     */
    public function patch($body, array $properties = [], ?array $options = null): Collection
    {
        if (is_numeric($properties)) {
            $options = $properties;
            $properties = [];
        }

        if (isset($options)) {
            $include_count = $options & QueryOptions::INCLUDE_COUNT;

            if ($include_count) {
                $this->totalCount = true;
            }
        }

        $original = $this->properties;

        if (is_null($original)) {
            $this->properties = $properties;
        }

        $results = $this->processor->processSelect($this, $this->runPatch($body));

        $this->properties = $original;

        return collect($results);
        //return $results;
    }

    /**
     * Run the query as a "GET" request against the client.
     *
     * @return array|string
     */
    protected function runGet(): array|string
    {
        return $this->client->get(
            $this->grammar->compileSelect($this), $this->getBindings()
        );
    }

    /**
     * Get a lazy collection for the given request.
     *
     * @return \Illuminate\Support\LazyCollection
     */
    public function cursor(): \Illuminate\Support\LazyCollection
    {
        return new LazyCollection(function() {
            yield from $this->client->cursor(
                $this->grammar->compileSelect($this), $this->getBindings()
            );
        });
    }

    /**
     * Run the query as a "GET" request against the client.
     *
     * @return array
     */
    protected function runPatch($body): array
    {
        return $this->client->patch(
            $this->grammar->compileSelect($this), $body
        );
    }

    /**
     * Run the query as a "GET" request against the client.
     *
     * @return array
     */
    protected function runPost($body): array
    {
        return $this->client->post(
            $this->grammar->compileSelect($this), $body
        );
    }

    /**
     * Run the query as a "GET" request against the client.
     *
     * @return array
     */
    protected function runDelete(): array
    {
        return $this->client->delete(
            $this->grammar->compileSelect($this)
        );
    }

    /**
     * Retrieve the "count" result of the query.
     *
     * @return int
     */
    public function count(): int
    {
        $this->count = true;
        $results = $this->get();

        if (! $results->isEmpty()) {
            // replace all none numeric characters before casting it as int
            return (int) preg_replace('/[^0-9,.]/', '', $results[0]);
        }

        return 0;
    }

    /**
     * Insert a new record into the database.
     *
     * @param array $values
     *
     * @return array|bool
     */
    public function insert(array $values): array|bool
    {
        // Since every insert gets treated like a batch insert, we will make sure the
        // bindings are structured in a way that is convenient when building these
        // inserts statements by verifying these elements are actually an array.
        if (empty($values)) {
            return true;
        }

        if (! is_array(reset($values))) {
            $values = [$values];
        }

        // Here, we will sort the insert keys for every record so that each insert is
        // in the same order for the record. We need to make sure this is the case
        // so there are not any errors or problems when inserting these records.
        else {
            foreach ($values as $key => $value) {
                ksort($value);

                $values[$key] = $value;
            }
        }

        // Finally, we will run this query against the database connection and return
        // the results. We will need to also flatten these bindings before running
        // the query so they are all in one huge, flattened array for execution.
        return $this->client->post(
            $this->grammar->compileInsert($this, $values),
            $this->cleanBindings(Arr::flatten($values, 1))
        );
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param array $values
     *
     * @return mixed
     */
    public function insertGetId(array $values): mixed
    {
        $results = $this->insert($values);

        return $results->getId();
    }

    /**
     * Get a new instance of the query builder.
     *
     * @return Builder
     */
    public function newQuery(): static
    {
        return new static($this->client, $this->grammar, $this->processor);
    }

    /**
     * Get the current query value bindings in a flattened array.
     *
     * @return array
     */
    public function getBindings(): array
    {
        return Arr::flatten($this->bindings);
    }

    /**
     * Remove all of the expressions from a list of bindings.
     *
     * @param array $bindings
     *
     * @return array
     */
    protected function cleanBindings(array $bindings): array
    {
        return array_values(array_filter($bindings, function ($binding) {
            return true;//! $binding instanceof Expression;
        }));
    }

    /**
     * Add a binding to the query.
     *
     * @param mixed  $value
     * @param string $type
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function addBinding(mixed $value, string $type = 'where'): static
    {
        if (! array_key_exists($type, $this->bindings)) {
            throw new \InvalidArgumentException("Invalid binding type: {$type}.");
        }

        if (is_array($value)) {
            $this->bindings[$type] = array_values(array_merge($this->bindings[$type], $value));
        } else {
            $this->bindings[$type][] = $value;
        }

        return $this;
    }

    /**
     * Get the IODataClient instance.
     *
     * @return IODataClient
     */
    public function getClient(): IODataClient
    {
        return $this->client;
    }
}
