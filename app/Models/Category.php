<?php
namespace App\Models;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\{Model,Builder};
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
use Illuminate\Database\Eloquent\Attributes\Fillable;
#[Fillable(['parent_id','is_active','sort_order'])]
class Category extends Model implements TranslatableContract {
 use Translatable;
 public array $translatedAttributes=['name','description'];
 protected function casts(): array {return ['is_active'=>'boolean','sort_order'=>'integer'];}
 public function parent(): BelongsTo {return $this->belongsTo(self::class,'parent_id');}
 public function children(): HasMany {return $this->hasMany(self::class,'parent_id');}
 public function scopeVisible(Builder $query,int $remaining=5): Builder {
  return $query->where('is_active',true)->where(function(Builder $q) use($remaining): void {
   $q->whereNull('parent_id');
   if($remaining>1) {$q->orWhereHas('parent',fn(Builder $p)=>$p->visible($remaining-1));}
  });
 }
}
