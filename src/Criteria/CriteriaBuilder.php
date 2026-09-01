<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Criteria;

use CaueSantos\LaravelRequestFilters\Contracts\ExtendedModelCriteria;
use CaueSantos\LaravelRequestFilters\Contracts\SearchableModelCriteria;
use CaueSantos\LaravelRequestFilters\Support\ComputedField;
use CaueSantos\LaravelRequestFilters\Support\RelationCounter;
use Closure;

/**
 * Fluent, ready-to-use {@see ExtendedModelCriteria} implementation. Declare
 * what a model allows and let the engine figure out how to do it:
 *
 *   public function criteria(): CriteriaBuilder
 *   {
 *       return CriteriaBuilder::make()
 *           ->setFilterable(['name', 'email', 'status', 'company.name'])
 *           ->setOrderable(['name', 'created_at', 'company.name'])
 *           ->setSelectable(['id', 'name', 'email', 'company.name'])
 *           ->computed('full_name', "CONCAT(first_name, ' ', last_name)")
 *           ->counter('completed_tasks_count', 'tasks', fn ($q) => $q->where('completed', true))
 *           ->filterUsing('status', fn ($query, $operator, $value) => $query->where(...));
 *   }
 *
 * `filterable()`/`orderable()`/`selectable()`/`relatable()` are the plain
 * {@see \CaueSantos\LaravelRequestFilters\Contracts\ModelCriteria} getters (a
 * strict `array` return, to stay covariant with that interface); use the
 * `set*` counterparts to configure them fluently. Everything else
 * (`computed()`, `counter()`, `filterUsing()`, `sortUsing()`, `alias()`) is
 * chainable directly since it has no getter/setter name clash.
 */
final class CriteriaBuilder implements SearchableModelCriteria, ModelCriteriaContract
{
    private array $filterable = ['*'];

    private array $orderable = ['*'];

    private array $selectable = ['*'];

    private array $relatable = ['*'];

    /** @var list<string> */
    private array $searchable = [];

    /** @var array<string, ComputedField> */
    private array $computedFields = [];

    /** @var array<string, RelationCounter> */
    private array $counters = [];

    /** @var array<string, Closure> */
    private array $customFilters = [];

    /** @var array<string, Closure> */
    private array $customSorts = [];

    /** @var array<string, string> */
    private array $aliases = [];

    public static function make(): self
    {
        return new self;
    }

    public function filterable(): array
    {
        return $this->filterable;
    }

    public function setFilterable(array $fields): static
    {
        $this->filterable = $fields;

        return $this;
    }

    public function orderable(): array
    {
        return $this->orderable;
    }

    public function setOrderable(array $fields): static
    {
        $this->orderable = $fields;

        return $this;
    }

    public function selectable(): array
    {
        return $this->selectable;
    }

    public function setSelectable(array $fields): static
    {
        $this->selectable = $fields;

        return $this;
    }

    public function relatable(): array
    {
        return $this->relatable;
    }

    public function setRelatable(array $fields): static
    {
        $this->relatable = $fields;

        return $this;
    }

    public function searchable(): array
    {
        return $this->searchable;
    }

    /** Fields (plain columns, computed fields, or dotted relation paths) the `q` search parameter may match against. */
    public function setSearchable(array $fields): static
    {
        $this->searchable = $fields;

        return $this;
    }

    /** Declare a computed field: its value is a SQL expression, not a real column. */
    public function computed(string $name, string|Closure $expression): static
    {
        $this->computedFields[$name] = ComputedField::make($name, $expression);

        return $this;
    }

    public function computedFields(): array
    {
        return $this->computedFields;
    }

    /** Declare a relation-count field, e.g. `tasks_count`, optionally constrained (e.g. only completed tasks). */
    public function counter(string $name, string $relation, ?Closure $constraint = null): static
    {
        $this->counters[$name] = RelationCounter::make($name, $relation, $constraint);

        return $this;
    }

    public function counters(): array
    {
        return $this->counters;
    }

    /** @param Closure(\Illuminate\Database\Eloquent\Builder, string $operator, mixed $value, string $boolean): void $callback */
    public function filterUsing(string $name, Closure $callback): static
    {
        $this->customFilters[$name] = $callback;

        return $this;
    }

    public function customFilters(): array
    {
        return $this->customFilters;
    }

    /** @param Closure(\Illuminate\Database\Eloquent\Builder, string $direction): void $callback */
    public function sortUsing(string $name, Closure $callback): static
    {
        $this->customSorts[$name] = $callback;

        return $this;
    }

    public function customSorts(): array
    {
        return $this->customSorts;
    }

    /** Let `$alias` be requested in place of `$realPath` (a real column or dotted relation path). */
    public function alias(string $alias, string $realPath): static
    {
        $this->aliases[$alias] = $realPath;

        return $this;
    }

    public function aliases(): array
    {
        return $this->aliases;
    }
}
