# Changelog

All notable changes to `laravel-request-filters` are documented here. The
format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
this project adheres to [Semantic Versioning](https://semver.org/).

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

[1.0.0]: https://github.com/ca-santos/laravel-request-filters/releases/tag/v1.0.0
