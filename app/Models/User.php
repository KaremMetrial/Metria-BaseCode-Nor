<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Enums\UserType;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * The single user model for every actor.
 *
 * `type` and `status` are deliberately absent from the fillable list: they are
 * security-relevant and must only ever be set by explicit code
 * (`forceFill`, a factory, or an Action), never by mass-assigning a request
 * payload.
 *
 * @property Carbon|null $phone_verified_at
 * @property UserType $type
 * @property UserStatus $status
 */
#[Fillable([
    'name',
    'email',
    'password',
    'locale',
    'timezone',
    'avatar_path',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    public function preferredLocale(): string
    {
        return in_array($this->locale, ['en', 'ar'], true) ? $this->locale : (string) config('languages.default');
    }

    protected function casts(): array
    {
        return [
            'type' => UserType::class,
            'status' => UserStatus::class,
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeOfType(Builder $query, UserType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeWithStatus(Builder $query, UserStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $this->scopeWithStatus($query, UserStatus::ACTIVE);
    }

    /*
    |--------------------------------------------------------------------------
    | State
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->status->isFullyActive();
    }

    public function isAdmin(): bool
    {
        return $this->type->isStaff();
    }

    public function hasVerifiedPhone(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function markPhoneAsVerified(): bool
    {
        return $this->forceFill(['phone_verified_at' => $this->freshTimestamp()])->save();
    }

    public function markAsLoggedIn(): bool
    {
        return $this->forceFill(['last_login_at' => $this->freshTimestamp()])->save();
    }

    /**
     * Whether an arbitrary phone value belongs to this user.
     *
     * Both sides are expected to already be canonical E.164; normalizing is the
     * phone service's job, not the model's.
     */
    public function hasPhone(?string $e164): bool
    {
        return $e164 !== null && $this->phone !== null && hash_equals($this->phone, $e164);
    }
}
