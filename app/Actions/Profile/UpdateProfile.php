<?php
namespace App\Actions\Profile;
use App\Models\User;
use Illuminate\Support\Arr;
final class UpdateProfile {
 public function execute(User $user,array $data): User {
  $user->fill(Arr::only($data,['name','locale','timezone']))->save();
  return $user;
 }
}
