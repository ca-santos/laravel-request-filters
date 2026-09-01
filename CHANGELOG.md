# Changelog

All notable changes to `laravel-request-filters` are documented here. The
format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
this project adheres to [Semantic Versioning](https://semver.org/).

## [1.2.0] - 2026-09-01

### Added
- Full-text search: `?q=term` matches any of the criteria's `searchable()`
  fields (an OR-ed `contains` across all of them) - plain columns, computed
  fields, and dotted relation paths are all supported, resolved the same way
  a `filters[field:contains]` condition on that field already would be. Opt
  in per model with `CriteriaBuilder::setSearchable([...])`; a criteria class
  that doesn't implement the new `SearchableModelCriteria` contract, or
  declares nothing searchable, is left untouched by `q`.
- Column-type-aware value casting: a filter value is now cast according to
  the real database column it's being compared against (via
  `SchemaIntrospector`), not just the value's own shape - a numeric-*looking*
  value in a text column (a status code, a zero-padded reference) is no
  longer silently coerced into a number just because it looks like one, while
  a value against a genuinely numeric column is still cast precisely. Applies
  to plain and single-relation columns; computed fields, counters, and custom
  filters are unaffected. Falls back to the previous generic heuristic
  whenever the real type can't be determined or isn't one of the ones this
  recognises.

### Fixed
- `ComplexFilterCriteria`'s multi-column ("concat" modifier) leaf fataled
  with "call to undefined method" whenever its columns belonged to a
  relation - `relationPathExists()` was `private` on the parent class,
  unreachable from that subclass. This code path had no prior test coverage.

## [1.1.0] - 2026-09-01

### Added
- Authorization for the `/filters/metadata` routes. They describe a model's
  real columns, relations, and attributes, and were previously reachable by
  any caller with no protection at all. They now respond `403` unless
  `app()->environment('local', 'testing')` is true; call
  `RequestFiltersServiceProvider::auth(fn ($request) => ...)` from your own
  service provider to allow (or further restrict) them, and/or list
  middleware classes under `config('laravel-request-filters.metadata_middleware')`
  to layer additional middleware (auth guards, rate limiting, ...) on top.

  **Potentially breaking**: an application that relied on calling
  `/filters/metadata` outside a local/testing environment with no
  authorization at all will now get `403` there until it calls
  `RequestFiltersServiceProvider::auth(...)`.

## [1.0.1] - 2026-09-01

### Fixed
- Sorting by a relation counter (`->counter(...)`, e.g. `order[asc]=posts_count`)
  previously produced a SQL error ("no such column") unless the exact same
  column had already been materialized via `count=<relation>`. `OrderByCriteria`
  now adds the matching `withCount()` subselect itself when needed (reusing
  one already selected under the same alias by `count=` or an earlier sort,
  rather than adding a duplicate), so a counter can always be sorted by
  directly, the same way a computed field already could.
- Raised the `phpunit/phpunit` floor from `^10.0` to `^10.1`. `orchestra/testbench:8.0.0`
  allows `phpunit/phpunit` as low as `10.0.7`, a version that predates the
  `<source>` element `phpunit.xml` uses, which made CI's `prefer-lowest` matrix
  jobs fail on an invalid-schema warning despite every test passing.

## [1.0.0] - 2026-09-01

First stable release: `Criteria/*` becomes a single, generic filtering engine
that works with any Eloquent model, and gains a large set of capabilities
that were never part of a published release before.

### Added
- Nested `complexFilters` query parameter: an arbitrarily deep AND/OR filter
  tree (`{"logic": "and", "filters": [...]}`), applied through Eloquent's own
  parenthesised-grouping (`where(Closure)`/`orWhere(Closure)`) instead of
  building a SQL string by hand.
- `CriteriaBuilder`, a fluent, ready-to-use implementation of the criteria
  contracts: `setFilterable()`/`setOrderable()`/`setSelectable()`/`setRelatable()`,
  plus additive capabilities with no prior equivalent:
  - `computed()` — a field whose value is a SQL expression, not a real column.
  - `counter()` — a relation-count field (optionally constrained), rewritten
    into a correlated `has()` existence check rather than comparing a
    `withCount()` alias directly.
  - `filterUsing()` / `sortUsing()` — take over filtering/sorting for a field
    entirely.
  - `alias()` — let a field be requested under a different public name than
    its real column.
- `Contracts\ModelCriteria` / `Contracts\ExtendedModelCriteria` /
  `Contracts\FiscalYearResolver`, replacing the framework-specific concepts
  (`BaseResource`, `Field`, `Filter`) the old engine's sibling
  (`ResourceCriteria/*`) depended on with generic, Eloquent-only equivalents.
- `count` query parameter — `?count=relation1,relation2` now actually
  annotates the query with `withCount()` (previously an unimplemented,
  silent no-op stub).
- Date shortcuts (`today`, `this_week`, `last_n_months`, `this_financial_year`,
  ...) as both a generic `date_<key>` filter operator and local model scopes
  via the `FilterableByDates` trait (`Model::whereDateIsLastNMonths(...)`).
- Relation-aware filtering, sorting, and column projection (`select`) across
  arbitrarily deep, nested relation paths (`company.posts.title`), resolved
  by calling the real relation method on the model and inspecting the
  `Relation` object it returns — never a consumer-provided metadata registry.
- `RequestFilterTrait::getFilterDefs()` and the `/filters/metadata`,
  `/filters/metadata/{table}` routes, describing a model's real columns,
  relations, and allowed filter/order/select/relate fields.
- `ResourceCriteria/*` as an explicitly legacy, `@deprecated` compatibility
  layer for existing direct consumers that instantiate those classes with a
  `BaseResource`/`BrikFormRequest` constructor — new code should use
  `Criteria/*` instead.
- A full test suite (73 tests / 153 assertions) covering filters, relations
  (including nested and invalid paths), computed fields, counters, sorting,
  selection, date shortcuts, and SQL-injection/LIKE-wildcard safety.
- `README.md` with a full query-parameter reference and verified complex
  query examples; CI via `.github/workflows/default.yml`.

### Changed
- Every filtering/sorting/selection stage (`ApplyCriteria`, `BaseCriteria`,
  `FilterCriteria`, `OrderByCriteria`, `SelectCriteria`, `CountCriteria`,
  `DefaultCriteria`) rewritten to build conditions with query bindings and
  structured Query Builder calls (`where`, `whereIn`, `whereBetween`,
  `whereHas`) instead of interpolating request values into raw SQL strings.
- `caue-santos/auto-class-discovery` requirement raised to the real
  Packagist release (`^1.0`, previously `dev-master` via a local path
  repository).
- Minimum PHP raised from `^7.4|^8` to `^8.2`; Laravel support narrowed from
  `^8.0|^9.0|^10.10` to `^10.10|^11.0`; `orchestra/testbench` raised from
  `^4.0|^5.0|^6.0` to `^8.0|^9.0`; `phpunit/phpunit` raised from `^8.4|^9.0`
  to `^10.0|^11.0`.
- `doctrine/dbal` moved from `require` to `suggest` — only needed for column
  metadata on Laravel <11, which lacks the native `Schema::getColumns()`.
- `phpunit.xml` modernized to the PHPUnit 11 schema (`<source>` instead of
  `<filter><whitelist>`).

### Removed
- Dead code confirmed unused by every consumer: `Criteria/RequestFilterBuilder.php`
  (an empty subclass, already overridden elsewhere), `Criteria/TranslatableQuerySearch.php`
  and `ResourceCriteria/TranslatableQuerySearch.php` (orphaned, never returned
  anything), `ResourceCriteria/SelectCriteria.php` (never called),
  `ResourceCriteria/ComplexFilterCriteria.php` (an unused duplicate), and the
  `mergeQueryColumns()` method on `ResourceCriteria/BaseCriteria` (only
  contained a leftover `dd()`).

### Fixed
- `ISNULL()` (a MySQL/MariaDB-only function) used in an `orderByRaw()` call
  broke on other drivers (e.g. SQLite) — replaced with the portable
  `(expr IS NULL)`.
- A value sanitization step was being applied to values already protected by
  query bindings, corrupting filter values that contained quotes or other
  special characters (silent false negatives).
- The `in` operator never split its value on commas.
- The discovery-cache fallback in `routes.php` crashed ("Trying to access
  array offset on null") whenever the cache hadn't been populated yet (e.g.
  in a test environment) — it now discovers on demand in that case instead.
- `RequestFilterTrait::sort()` called an undefined `ApplyCriteria::sort()`
  and always threw; `ApplyCriteria::sort()` now exists and works.

[1.2.0]: https://github.com/ca-santos/laravel-request-filters/releases/tag/v1.2.0
[1.1.0]: https://github.com/ca-santos/laravel-request-filters/releases/tag/v1.1.0
[1.0.1]: https://github.com/ca-santos/laravel-request-filters/releases/tag/v1.0.1
[1.0.0]: https://github.com/ca-santos/laravel-request-filters/releases/tag/v1.0.0
