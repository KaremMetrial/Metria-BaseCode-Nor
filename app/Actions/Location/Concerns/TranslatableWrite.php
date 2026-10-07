<?php

namespace App\Actions\Location\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Shared persistence for the translatable location writes.
 *
 * Three things here are load-bearing and easy to get wrong by hand:
 *
 *  1. `fill()` is Astrotomic-aware. Given the `translations` wrapper it creates
 *     or updates the matching translation rows, and it only touches the locales
 *     actually present in the payload -- so PATCHing the Arabic name cannot
 *     erase the English one.
 *  2. Only `saved`-dirty translations are written, so an idempotent re-import
 *     does not churn `updated_at` on every row.
 *  3. The row and its translations share one transaction. A country saved
 *     without its mandatory English translation renders as an empty name and
 *     would have to be repaired by hand; a half-written record must not exist.
 */
trait TranslatableWrite
{
    /**
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    protected function persist(Model $model, array $data): Model
    {
        return DB::transaction(function () use ($model, $data): Model {
            $model->fill($data);
            $model->save();

            // Reload so the caller (and the API Resource) sees every translation,
            // not just the ones this request happened to touch.
            return $model->load('translations');
        });
    }
}
