<?php

namespace App\Support;

use App\Http\Requests\Shared\ListRequest;
use Astrotomic\Translatable\Contracts\Translatable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class ListQuery
{
    /**
     * Apply only to an already authorized/visibility-scoped query.
     *
     * @template T of Model
     *
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    public function apply(Builder $query, ListRequest $request): Builder
    {
        $data = $request->validated();
        foreach ($request::FILTERS as $field => $rules) {
            if (array_key_exists($field, $data) && ! in_array($field, $request::VIRTUAL_FILTERS, true)) {
                if (is_array($data[$field])) {
                    $query->whereIn($query->qualifyColumn($field), $data[$field]);
                } else {
                    $query->where($query->qualifyColumn($field), $data[$field]);
                }
            }
        }
        foreach ($request::NULLS as $field => $column) {
            if (array_key_exists($field, $data)) {
                $query->whereNull($query->qualifyColumn($column), not: ! (bool) $data[$field]);
            }
        }
        foreach ($request::NOT_NULLS as $field => $column) {
            if (array_key_exists($field, $data)) {
                $query->whereNull($query->qualifyColumn($column), not: (bool) $data[$field]);
            }
        }
        foreach ($request::DATES as $name => $column) {
            if (isset($data[$name.'_from'])) {
                $query->where($query->qualifyColumn($column), '>=', CarbonImmutable::parse($data[$name.'_from'], 'UTC')->startOfDay());
            }
            if (isset($data[$name.'_to'])) {
                $query->where($query->qualifyColumn($column), '<', CarbonImmutable::parse($data[$name.'_to'], 'UTC')->addDay()->startOfDay());
            }
        }
        foreach ($request::RANGES as $name => $column) {
            foreach (['min' => '>=', 'max' => '<='] as $suffix => $operator) {
                if (isset($data[$name.'_'.$suffix])) {
                    $query->where($query->qualifyColumn($column), $operator, $data[$name.'_'.$suffix]);
                }
            }
        }
        $search = $data['search'] ?? $data['keyword'] ?? null;
        if ($search !== null) {
            $query->where(function (Builder $nested) use ($request, $search): void {
                foreach ($request::SEARCH as $column => $mode) {
                    $nested->orWhere($nested->qualifyColumn($column), $mode === 'exact' ? '=' : 'like', $mode === 'exact' ? $search : '%'.$search.'%');
                }
                if ($request::TRANSLATED !== null) {
                    $expression = $this->translation($nested, $request::TRANSLATED);
                    $nested->orWhereRaw('('.$expression->toSql().') like ?', [...$expression->getBindings(), '%'.$search.'%']);
                }
            });
        }
        $query->reorder();
        foreach ($request->sortTerms() as [$field, $direction]) {
            if ($field === $request::TRANSLATED) {
                $query->orderBy($this->translation($query, $field), $direction);
            } else {
                $column = $query->qualifyColumn($field);
                $wrapped = $query->getQuery()->getGrammar()->wrap($column);
                if (in_array($field, $request::NULLABLE_SORTS, true)) {
                    $query->orderByRaw($wrapped.' IS NULL asc');
                }
                $query->orderBy($column, $direction);
            }
        }

        return $query;
    }

    /**
     * Scalar subquery avoids multiplying parents and follows configured locale fallback.
     *
     * @template T of Model
     *
     * @param  Builder<T>  $query
     */
    private function translation(Builder $query, string $column): QueryBuilder
    {
        $model = $query->getModel();
        if (! $model instanceof Translatable) {
            throw new \LogicException('Translated filtering requires a translatable model.');
        }
        $relation = $model->translations();
        $related = $relation->getRelated();
        $fallback = config('translatable.fallback_locale');
        $locales = array_values(array_unique([app()->getLocale(), ...(is_string($fallback) ? [$fallback] : config('translatable.locales', ['en', 'ar']))]));
        $grammar = $query->getQuery()->getGrammar();
        $localeColumn = $grammar->wrap($related->qualifyColumn('locale'));
        $order = 'CASE '.$localeColumn;
        foreach ($locales as $index => $locale) {
            $order .= ' WHEN ? THEN '.$index;
        }
        $order .= ' ELSE '.count($locales).' END';

        return $related->newQuery()->select($column)
            ->whereColumn($relation->getQualifiedForeignKeyName(), $model->getQualifiedKeyName())
            ->whereIn('locale', $locales)->whereNotNull($column)->where($column, '<>', '')
            ->orderByRaw($order, $locales)->limit(1)->toBase();
    }
}
