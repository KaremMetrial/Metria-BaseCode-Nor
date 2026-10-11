<?php

namespace App\Actions\Shared\Contact;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\Auth\AuthenticationPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SubmitContactMessage
{
    public function __construct(private readonly AuthenticationPhone $phones) {}

    /** @param array<string, mixed> $data */
    public function execute(array $data, ?User $user): ContactMessage
    {
        $phone = $this->phones->parse((int) $data['country_id'], $data['phone'])->e164;
        $values = ['user_id' => $user?->id, 'name' => $data['name'], 'phone' => $phone, 'subject' => $data['subject'], 'message' => $data['message']];
        $hash = hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($data, $values, $hash): ContactMessage {
            ContactMessage::query()->insertOrIgnore($values + ['request_id' => $data['request_id'], 'request_hash' => $hash, 'reference' => (string) Str::uuid(), 'status' => 'new', 'created_at' => now(), 'updated_at' => now()]);
            $message = ContactMessage::query()->where('request_id', $data['request_id'])->firstOrFail();
            if (! hash_equals($message->request_hash, $hash)) {
                throw new DomainException(ErrorCode::IDEMPOTENCY_CONFLICT);
            }

            return $message;
        }, 5);
    }
}
