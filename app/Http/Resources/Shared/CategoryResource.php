<?php
namespace App\Http\Resources\Shared;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class CategoryResource extends JsonResource {
 public function toArray(Request $request): array {return ['id'=>$this->id,'parent_id'=>$this->parent_id,'name'=>$this->name,'description'=>$this->description,'is_active'=>$this->is_active,'sort_order'=>$this->sort_order,'translations'=>$this->when($request->user()?->isAdmin() && $request->user()?->can('categories.read'),fn()=>$this->translations->mapWithKeys(fn($t)=>[$t->locale=>['name'=>$t->name,'description'=>$t->description]]))];}
}
