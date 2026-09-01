# Laravel Request Filters

[![GitHub Workflow Status](https://github.com/ca-santos/laravel-request-filters/workflows/Run%20tests/badge.svg)](https://github.com/ca-santos/laravel-request-filters/actions)
[![Packagist](https://img.shields.io/packagist/v/caue-santos/laravel-request-filters.svg)](https://packagist.org/packages/caue-santos/laravel-request-filters)
[![Packagist](https://poser.pugx.org/caue-santos/laravel-request-filters/d/total.svg)](https://packagist.org/packages/caue-santos/laravel-request-filters)
[![Packagist](https://img.shields.io/packagist/l/caue-santos/laravel-request-filters.svg)](https://packagist.org/packages/caue-santos/laravel-request-filters)

Turn an HTTP request's query string into safe, whitelisted Eloquent query
constraints — flat filters, arbitrarily nested AND/OR filter trees, relation
traversal (including nested relations), computed fields, relation counters,
column projection, `withCount()` annotation, and relation-aware sorting —
without writing a single line of ad-hoc query-building code per endpoint.

Every value is applied through query bindings or structured Query Builder
calls (`where`, `whereIn`, `whereBetween`, `whereHas`, ...); the only raw SQL
fragments ever built are assembled exclusively from whitelisted **column
identifiers**, never from request values.

## Requirements

- PHP ^8.2
- Laravel ^10.10 or ^11.0

## Installation

```bash
composer require caue-santos/laravel-request-filters
```

The service provider is auto-discovered. To publish the config file:

```bash
php artisan vendor:publish --provider="CaueSantos\LaravelRequestFilters\RequestFiltersServiceProvider"
```

```php
// config/laravel-request-filters.php
return [
    // Where the /filters/metadata endpoint looks for filterable models.
    'models_folder' => app_path('Models'),
];
```

## Core concepts

| Concept | What it is |
|---|---|
| `ModelCriteria` | The 4-method whitelist contract every filterable model exposes: `filterable()`, `orderable()`, `selectable()`, `relatable()`. |
| `ExtendedModelCriteria` | Optional additive contract: `computedFields()`, `counters()`, `customFilters()`, `customSorts()`, `aliases()`. |
| `CriteriaBuilder` | Fluent, ready-to-use implementation of both contracts above — you'll use this in almost every model. |
| `DefaultCriteria` | The "no restrictions" criteria (`['*']` everywhere) — used when a model doesn't define its own. |
| `RequestFilterTrait` | Add to a model to get `Model::applyCriteria()`, `Model::sort()` and `Model::getFilterDefs()`. |
| `ApplyCriteria` | The pipeline orchestrator — inspects the current request for `complexFilters`, `filters`, `select`, `count`, `order` and applies whichever are present, in that order. |

## Setting up a model

```php
use CaueSantos\LaravelRequestFilters\Criteria\CriteriaBuilder;
use CaueSantos\LaravelRequestFilters\Criteria\ModelCriteriaContract;
use CaueSantos\LaravelRequestFilters\Criteria\RequestFilterTrait;
use CaueSantos\LaravelRequestFilters\Support\ColumnResolver;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use RequestFilterTrait;

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public static function criteria(): string|ModelCriteriaContract
    {
        return CriteriaBuilder::make()
            ->setFilterable(['first_name', 'last_name', 'email', 'status', 'age', 'company.name', 'posts.title'])
            ->setOrderable(['first_name', 'last_name', 'age', 'created_at', 'company.name'])
            ->setSelectable(['*'])
            ->setRelatable(['company', 'posts', 'posts.tags'])
            // A field whose value is a SQL expression, not a real column.
            ->computed('full_name', fn ($query) => ColumnResolver::concat($query, ['first_name', 'last_name']))
            // A relation-count field, optionally constrained.
            ->counter('posts_count', 'posts')
            ->counter('published_posts_count', 'posts', fn ($q) => $q->whereNotNull('published_at'))
            // Take over filtering for a field entirely.
            ->filterUsing('is_adult', function ($query, string $operator, mixed $value) {
                $value ? $query->where('age', '>=', 18) : $query->where('age', '<', 18);
            })
            // Take over sorting for a field entirely.
            ->sortUsing('name_reversed', function ($query, string $direction) {
                $query->orderByRaw("last_name {$direction}, first_name {$direction}");
            })
            // Let `email_address` be requested in place of the real `email` column.
            ->alias('email_address', 'email');
    }
}
```

`setFilterable(['*'])` (the default) allows every field. To allow everything
**except** a few fields, whitelist `*` and exclude with a `!` prefix:

```php
->setFilterable(['*', '!password', '!remember_token'])
```

Then, in a controller:

```php
public function index()
{
    return User::applyCriteria(User::criteria())->paginate();
}
```

`applyCriteria()` reads the *current* request (`request()->query()`) — there's
nothing else to wire up per endpoint.

## Query parameters reference

| Parameter | Purpose |
|---|---|
| `filters[field:operator:modifier]=value` | Flat filters, implicitly AND-ed together. |
| `complexFilters` | Arbitrarily nested AND/OR filter tree. |
| `select` | Column projection, including relation columns. |
| `count` | `withCount()` one or more relations. |
| `order[asc]` / `order[desc]` | Comma-separated columns/relation paths to sort by. |

All five can be combined in the same request; they're applied in the order
above (`complexFilters` → `filters` → `select` → `count` → `order`).

### Simple filters — `filters`

```
GET /users?filters[status:eq]=active&filters[age:gte]=18
```

Key shape: `field[:operator[:modifier]]`. Omitting the operator defaults to `eq`.
A comma-separated value becomes an array (used by `in`/`between`):

```
GET /users?filters[status:in]=active,pending&filters[age:between]=18,65
```

A field not present in `filterable()` is **silently dropped** — the rest of
the request still runs.

### Complex nested filters — `complexFilters`

```json
{
    "logic": "and",
    "filters": [
        { "column": "status", "operator": "eq", "value": "active" },
        {
            "logic": "or",
            "filters": [
                { "column": "company.name", "operator": "eq", "value": "Acme" },
                { "column": "published_posts_count", "operator": "gte", "value": "5" }
            ]
        },
        { "column": "age", "operator": "between", "value": "18,65" }
    ]
}
```

As a query string (PHP's standard bracket-array encoding — this is exactly
what `URLSearchParams`/`qs`-style nested serialization produces, and what
`$request->query()` parses back into the tree above):

```
GET /users?complexFilters[logic]=and
    &complexFilters[filters][0][column]=status
    &complexFilters[filters][0][operator]=eq
    &complexFilters[filters][0][value]=active
    &complexFilters[filters][1][logic]=or
    &complexFilters[filters][1][filters][0][column]=company.name
    &complexFilters[filters][1][filters][0][operator]=eq
    &complexFilters[filters][1][filters][0][value]=Acme
    &complexFilters[filters][1][filters][1][column]=published_posts_count
    &complexFilters[filters][1][filters][1][operator]=gte
    &complexFilters[filters][1][filters][1][value]=5
    &complexFilters[filters][2][column]=age
    &complexFilters[filters][2][operator]=between
    &complexFilters[filters][2][value]=18,65
```

Groups nest to any depth; each group's `logic` (`and`/`or`) only affects its
own children. A leaf's `column` may be a comma-separated list paired with the
`"modifier": "concat"` key, to compare a concatenation of several columns
(e.g. `"column": "company.name,company.city"` with `"modifier": "concat"`) —
supported when every column is local, or every column belongs to the *same*
relation path.

### Column projection — `select`

```
GET /users?select=id,first_name,company.name,posts.title
```

A relation column is loaded through a constrained eager-load — Eloquent still
hydrates `$user->company` and `$user->posts`, just with only the requested
columns (plus whatever key(s) Eloquent needs to match parents back to
children, added automatically).

### Relation counts — `count`

```
GET /users?count=posts,company
```

Adds `posts_count` and `company_count` to every result via `withCount()`.
Each relation is checked against `relatable()`.

### Sorting — `order`

```
GET /users?order[asc]=first_name,last_name&order[desc]=age
```

Supports plain columns, computed fields, custom sorts (`sortUsing`), and
relation paths of any depth (`order[asc]=company.name`,
`order[asc]=company.posts.title`) — the engine adds whatever `LEFT JOIN`s are
needed, aliased and `GROUP BY`-ed so a to-many relation doesn't duplicate
rows.

`Model::sort()` (relation-aware "smart" sort) never throws on a disallowed or
unresolvable column — it's dropped instead — and falls back to a sensible
default (the primary key, or `created_at` for a non-integer key) when nothing
was requested. The plain `OrderByCriteria::apply()` used internally by
`applyCriteria()` **throws `InvalidArgumentException`** for a column outside
`orderable()`.

## Operators

| Operator | Meaning | Negated variant |
|---|---|---|
| `eq` | `=` | `!eq` → `!=` |
| `lt` / `lte` / `gt` / `gte` | `<` / `<=` / `>` / `>=` | — |
| `contains` | `LIKE %value%` | `!contains` |
| `starts` | `LIKE value%` | `!starts` |
| `ends` | `LIKE %value` | `!ends` |
| `empty` | `IS NULL OR = ''` (or, on a relation field, "has no related row") | `!empty` |
| `in` | `IN (...)` | `!in` |
| `between` | `BETWEEN a AND b` | `!between` |
| `date_<shortcut>` | one of the date shortcuts below | — |

`LIKE` wildcards (`%`, `_`) inside a user-supplied value are always escaped —
searching for a literal `%` or `_` matches literally, it doesn't become a
wildcard.

## Date shortcuts

Available as the `date_<key>` filter operator (`filters[published_at:date_last_n_months]=3`)
and as a local scope on any model using the `FilterableByDates` trait
(`Post::whereDateIsLastNMonths('published_at', 3)`):

`today`, `yesterday`, `tomorrow`,
`this_week` / `last_week` / `next_week` / `last_n_weeks` / `next_n_weeks` / `n_weeks_ago`,
`this_month` / `last_month` / `next_month` / `last_n_months` / `next_n_months` / `n_months_ago`,
`last_n_days` / `next_n_days` / `n_days_ago`,
`this_quarter` / `last_quarter` / `next_quarter` / `last_n_quarters` / `next_n_quarters` / `n_quarters_ago`,
`this_year` / `last_year` / `next_year` / `last_n_years` / `next_n_years` / `n_years_ago`,
`this_financial_year` / `last_financial_year` / `next_financial_year`.

Shortcuts prefixed `last_n_`/`next_n_`/`n_..._ago` take the filter *value* as
their `n` (`filters[created_at:date_last_n_days]=7`). Every range is
half-open (`to` excluded) except the three rolling-window day shortcuts
(`last_n_days`, `next_n_days`, `n_days_ago`), which are closed ranges anchored
to *now*.

Fiscal-year shortcuts resolve via the `Contracts\FiscalYearResolver`
interface, bound by default to a resolver assuming a UK-style April–March
year; register your own binding in your app's service provider to change it.

## Relations, computed fields, and counters

- **Relation filters**: `filters[company.name:eq]=Acme`, `filters[posts.title:contains]=hello`,
  arbitrarily nested (`filters[users.posts.title:contains]=hello` on a `Company`).
  An unresolvable relation path is silently dropped, not an error.
- **Relation existence**: `filters[posts.title:!empty]=1` ("has at least one post"),
  `filters[posts.title:empty]=1` ("has no posts").
- **Computed fields** (`->computed(...)`) are never compared/ordered by their
  bare alias (SQL doesn't allow referencing a SELECT alias in `WHERE`) — the
  engine always substitutes the resolved expression instead, so
  `filters[full_name:contains]=John Smith` and `order[asc]=full_name` both work.
- **Relation counters** (`->counter(...)`) are rewritten into a correlated
  `has($relation, $operator, $count)` existence check rather than comparing
  the `withCount()` alias directly — `filters[posts_count:gte]=5`,
  `filters[posts_count:between]=1,10` both work as filters.
  ⚠️ Unlike computed fields, a counter is **not** substituted when used in
  `order` — sorting by a counter's name only works once it has been
  materialized as a real selected column via `count=<relation>` (which
  produces a real `<relation>_count` column you can then sort by); sorting by
  an arbitrary counter alias that was never selected will fail with a SQL
  error ("no such column").

## Aliases and whitelists

- `->alias('email_address', 'email')` lets `filters[email_address:eq]=...` /
  `order[asc]=email_address` be requested in place of the real `email` column
  — useful for renaming a field for API consumers without touching the schema.
- Every whitelist (`filterable`, `orderable`, `selectable`, `relatable`)
  follows the same rule: `['*']` allows everything; otherwise a field must be
  explicitly listed; either way, a `'!field'` entry always excludes it.

## Metadata endpoint

The package registers two routes (prefixed `/filters`):

```
GET /filters/metadata            # every model using RequestFilterTrait, discovered under models_folder
GET /filters/metadata/{table}    # one model, looked up by table name
```

Each entry is the same shape `Model::getFilterDefs()` returns:

```php
[
    'model' => User::class,
    'table' => 'users',
    'fillable' => [...],
    'attributes' => [...],
    'columns' => [...],       // real DB columns, via Schema::getColumns()
    'allowed' => [
        'filterable' => [...],
        'orderable' => [...],
        'selectable' => [...],
        'relatable' => [...],
    ],
    'relations' => [...],     // relation name => related model class
]
```

## Complex query examples

These are exact, verified requests against the `Company (hasMany) → User
(hasMany) → Post (belongsToMany) → Tag` domain used in this package's own
test suite.

### 1. Nested AND/OR combining a relation, a counter, and a range

> "Active users whose company is Acme **or** who have at least 5 published
> posts, and who are between 18 and 65 years old."

```json
{
    "logic": "and",
    "filters": [
        { "column": "status", "operator": "eq", "value": "active" },
        {
            "logic": "or",
            "filters": [
                { "column": "company.name", "operator": "eq", "value": "Acme" },
                { "column": "published_posts_count", "operator": "gte", "value": "5" }
            ]
        },
        { "column": "age", "operator": "between", "value": "18,65" }
    ]
}
```

Generated SQL (MySQL-flavoured; the engine also runs unmodified on SQLite/PostgreSQL):

```sql
select * from `users`
where (
    `users`.`status` = ?
    and (
        exists (select * from `companies` where `users`.`company_id` = `companies`.`id` and `companies`.`name` = ?)
        or (select count(*) from `posts` where `users`.`id` = `posts`.`user_id` and `published_at` is not null) >= ?
    )
    and (`users`.`age` between ? and ?)
)
```

### 2. Combine `complexFilters` and flat `filters` in the same request, plus `select`, `count` and relation sort

```
GET /users
    ?complexFilters[logic]=and
        &complexFilters[filters][0][column]=status
        &complexFilters[filters][0][operator]=eq
        &complexFilters[filters][0][value]=active
        &complexFilters[filters][1][logic]=or
        &complexFilters[filters][1][filters][0][column]=company.name
        &complexFilters[filters][1][filters][0][operator]=eq
        &complexFilters[filters][1][filters][0][value]=Acme
        &complexFilters[filters][1][filters][1][column]=published_posts_count
        &complexFilters[filters][1][filters][1][operator]=gte
        &complexFilters[filters][1][filters][1][value]=5
        &complexFilters[filters][2][column]=age
        &complexFilters[filters][2][operator]=between
        &complexFilters[filters][2][value]=18,65
    &filters[full_name:contains]=Alice
    &select=id,first_name,company.name
    &count=posts
    &order[asc]=company.name&order[desc]=age
```

Both `complexFilters` and `filters` are applied (AND-ed together) in the same
query — the flat `filters[full_name:contains]` narrows the result of the
nested tree above even further, `select` projects only the requested columns
(still eager-loading `company` for the requested `company.name`), `count=posts`
adds a real `posts_count` column, and the final ordering joins `companies`
(aliased, so it doesn't collide with other joins) and falls back to `age`.

### 3. Relation counter `between` combined with a negated `in`

> "Users with 1 to 10 posts, whose status is neither `banned` nor `deleted`."

```
GET /users?complexFilters[logic]=and
    &complexFilters[filters][0][column]=posts_count
    &complexFilters[filters][0][operator]=between
    &complexFilters[filters][0][value]=1,10
    &complexFilters[filters][1][column]=status
    &complexFilters[filters][1][operator]=!in
    &complexFilters[filters][1][value]=banned,deleted
```

### 4. Date shortcut with an `n` argument, scoped to a relation's own table

> "Posts published in the last 7 days."

```
GET /posts?filters[published_at:date_last_n_days]=7
```

Equivalent, expressed as a local scope instead of a request:

```php
Post::whereDateIsLastNDays('published_at', 7)->get();
```

### 5. Deeply nested relation filter, sort, and select (3 levels)

> "Companies that have a user with a post whose title contains a phrase" —
> filtered, sorted, and selected all the way down to the third relation hop.

```
GET /companies
    ?filters[users.posts.title:contains]=quarterly report
    &order[asc]=users.posts.title
    &select=name,users.posts.title
```

## Security model

- Every value is bound (`where`, `whereIn`, `whereBetween`, parameterised
  `whereRaw(... ? ...)`) — never interpolated into SQL.
- `LIKE` wildcards in `contains`/`starts`/`ends` values are escaped, with a
  bound `ESCAPE` character portable across MySQL/SQLite/PostgreSQL.
- Column/relation names coming from the request are validated against an
  allowed character set and the model's own whitelist before ever reaching a
  raw SQL fragment — an attempt like `order[asc]=id; DROP TABLE users;--` is
  dropped, not executed.
- A field/relation outside the whitelist is dropped silently (`filters`,
  `select`, `count`, `Model::sort()`) or throws `InvalidArgumentException`
  (`OrderByCriteria::apply()`, i.e. plain `applyCriteria()` ordering) — pick
  `sort()` over `applyCriteria()`'s own order handling when you want
  disallowed input to degrade gracefully instead of failing the request.

## Testing

```bash
composer install
vendor/bin/phpunit            # or: vendor/bin/phpunit --testdox
```

## Security

If you discover any security related issues, please email cauesantosre4@gmail.com instead of using the issue tracker.

## Credits

- [Caue Santos](https://github.com/ca-santos)
- [All contributors](https://github.com/ca-santos/laravel-request-filters/graphs/contributors)
