<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $locale
 * @property string $title
 * @property string $content
 */
class PageTranslation extends Model
{
    public $timestamps = false;

    protected $fillable = ['title', 'content'];
}
