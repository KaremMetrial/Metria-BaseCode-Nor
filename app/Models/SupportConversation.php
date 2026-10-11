<?php

namespace App\Models;

use Database\Factories\SupportConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportConversation extends Model
{
    /** @use HasFactory<SupportConversationFactory> */
    use HasFactory;

    protected $hidden = ['request_id', 'request_hash'];

    protected function casts(): array
    {
        return ['last_message_id' => 'integer', 'client_read_through' => 'integer', 'staff_read_through' => 'integer', 'version' => 'integer'];
    }

    /** @return HasMany<SupportMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class);
    }
}
