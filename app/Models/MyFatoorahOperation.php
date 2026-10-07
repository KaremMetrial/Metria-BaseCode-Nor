<?php

namespace App\Models;

use Database\Factories\MyFatoorahOperationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property array<string|int, mixed>|null $result */
final class MyFatoorahOperation extends Model
{
    /** @use HasFactory<MyFatoorahOperationFactory> */
    use HasFactory;

    protected $hidden = ['request_hash', 'idempotency_key', 'result'];

    protected function casts(): array
    {
        return ['result' => 'encrypted:array'];
    }
}
