<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Translated columns for Country.
 *
 * `$fillable` matters here: Astrotomic writes translations with `fill()`, so a
 * column missing from this list would be silently discarded instead of saved.
 *
 * @property string $locale
 * @property string $name
 * @property string|null $nationality
 */
#[Fillable(['name', 'nationality'])]
class CountryTranslation extends Model
{
    //
}
