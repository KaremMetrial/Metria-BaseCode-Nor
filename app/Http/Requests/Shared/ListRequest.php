<?php

namespace App\Http\Requests\Shared;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Explicit list contracts; authorization and ownership remain on the route and base query. */
abstract class ListRequest extends FormRequest
{
    public const SORTS = ['id', 'created_at'];

    public const NULLABLE_SORTS = [];

    public const DEFAULT_SORT = '-id';

    public const PAGINATED = true;

    public const SEARCH = [];

    public const TRANSLATED = null;

    public const FILTERS = [];

    public const MULTI = [];

    public const VIRTUAL_FILTERS = [];

    public const DATES = ['created' => 'created_at'];

    public const RANGES = [];

    public const NULLS = [];

    public const NOT_NULLS = [];

    public const CURRENCY_RANGE = null;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = ['sort' => ['sometimes', 'string', 'max:100'], 'sort_type' => ['sometimes', 'required_with:sort', 'in:asc,desc']];
        if (static::PAGINATED) {
            $rules += ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
        }
        if (static::SEARCH !== [] || static::TRANSLATED !== null) {
            $rules['search'] = ['sometimes', 'string', 'min:1', 'max:100'];
        }
        foreach (static::FILTERS as $field => $validation) {
            $rules[$field] = ['sometimes', ...$validation];
            if (in_array($field, static::MULTI, true) && is_array($this->input($field))) {
                $rules[$field] = ['sometimes', 'array', 'list', 'min:1', 'max:20'];
                $rules[$field.'.*'] = ['required', 'distinct', ...$validation];
            }
        }
        foreach (static::DATES as $name => $column) {
            $rules[$name.'_from'] = ['sometimes', 'date_format:Y-m-d'];
            $rules[$name.'_to'] = ['sometimes', 'date_format:Y-m-d'];
        }
        foreach (static::RANGES as $name => $column) {
            $rules[$name.'_min'] = ['sometimes', 'integer', 'min:0', 'max:9000000000000000'];
            $rules[$name.'_max'] = ['sometimes', 'integer', 'min:0', 'max:9000000000000000'];
        }
        foreach ([...static::NULLS, ...static::NOT_NULLS] as $name => $column) {
            $rules[$name] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->query()), [...array_keys($this->rules()), 'locale']) as $field) {
                $validator->errors()->add($field, __('filters.unsupported'));
            }
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($this->has('sort_type') && (! $this->filled('sort') || str_contains($this->input('sort'), ',') || str_starts_with($this->input('sort'), '-'))) {
                $validator->errors()->add('sort_type', __('filters.sort_type'));
            }
            $sorts = explode(',', $this->input('sort', static::DEFAULT_SORT));
            $fields = array_map(fn (string $sort): string => ltrim($sort, '-'), $sorts);
            if (count($sorts) > 3 || count(array_unique($fields)) !== count($fields)) {
                $validator->errors()->add('sort', __('filters.sort'));
            }
            foreach ($sorts as $sort) {
                if (! preg_match('/^-?[a-z][a-z0-9_]*$/D', $sort) || ! in_array(ltrim($sort, '-'), static::SORTS, true)) {
                    $validator->errors()->add('sort', __('filters.sort'));
                    break;
                }
            }
            foreach (static::DATES as $name => $column) {
                if ($this->filled([$name.'_from', $name.'_to']) && $this->input($name.'_from') > $this->input($name.'_to')) {
                    $validator->errors()->add($name.'_to', __('filters.range'));
                }
            }
            foreach (static::RANGES as $name => $column) {
                if ($this->filled([$name.'_min', $name.'_max']) && $this->input($name.'_min') > $this->input($name.'_max')) {
                    $validator->errors()->add($name.'_max', __('filters.range'));
                }
            }
            $money = static::CURRENCY_RANGE;
            if ($money && ($this->hasAny([$money.'_min', $money.'_max']) || in_array($money, $fields, true)) && ! $this->filled('currency')) {
                $validator->errors()->add('currency', __('filters.currency'));
            }
            $this->validateFilters($validator);
        }];
    }

    protected function validateFilters(Validator $validator): void {}

    /** @return list<array{0: string, 1: string}> */
    public function sortTerms(): array
    {
        $terms = [];
        foreach (explode(',', $this->validated('sort', static::DEFAULT_SORT)) as $sort) {
            $terms[] = [ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : $this->validated('sort_type', 'asc')];
        }
        if (! in_array('id', array_column($terms, 0), true)) {
            $terms[] = ['id', $terms[0][1]];
        }

        return $terms;
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 25);
    }
}
