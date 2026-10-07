<?php
namespace App\Http\Resources\Shared;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class UserResource extends JsonResource {
 public function toArray(Request $request): array { return ['id'=>$this->id,'name'=>$this->name,'email'=>$this->email,'phone'=>$this->phone,'phone_country_id'=>$this->phone_country_id,'phone_verified_at'=>$this->phone_verified_at?->toIso8601String(),'type'=>$this->type->value,'status'=>$this->status->value,'locale'=>$this->locale,'timezone'=>$this->timezone]; }
}
