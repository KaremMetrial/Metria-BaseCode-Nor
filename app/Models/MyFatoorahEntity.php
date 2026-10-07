<?php

namespace App\Models;

use Database\Factories\MyFatoorahEntityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property array<string|int, mixed> $snapshot
 * @property array<string, mixed>|null $last_event
 * @property Carbon|null $synced_at
 */
final class MyFatoorahEntity extends Model
{
    /** @use HasFactory<MyFatoorahEntityFactory> */
    use HasFactory;

    protected $fillable = ['kind', 'reference', 'snapshot', 'status'];

    protected $hidden = ['snapshot', 'last_event'];

    protected function casts(): array
    {
        return ['snapshot' => 'encrypted:array', 'last_event' => 'encrypted:array', 'needs_refresh' => 'boolean', 'synced_at' => 'datetime'];
    }
}
