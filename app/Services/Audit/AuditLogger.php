<?php

namespace App\Services\Audit;

use App\Contracts\Audit\AuditLoggerInterface;
use App\DTOs\Audit\AuditEntry;
use App\Models\AuditLog;
use App\Support\RequestId;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Writes audit rows to the database.
 *
 * Two defensive behaviours matter here:
 *
 *  1. Secrets are redacted before storage. Passwords, tokens and OTP codes must
 *     never be persisted, and relying on "callers will remember" is not a
 *     control — the redaction is applied to the data, not requested of callers.
 *  2. Request metadata is only read when an HTTP request actually exists, so a
 *     queue worker cannot attribute a job's action to the last request object.
 */
final class AuditLogger implements AuditLoggerInterface
{
    private const REDACTED = '[REDACTED]';

    /**
     * Keys whose values must never be persisted.
     *
     * Deliberately does not include generic names like "code" or "id", which
     * appear legitimately in many contexts; over-redaction makes an audit log
     * useless.
     *
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'remember_token',
        'token',
        'access_token',
        'refresh_token',
        'api_key',
        'private_key',
        'secret',
        'otp',
        'otp_code',
    ];

    public function __construct(private readonly RequestId $requestId) {}

    public function record(AuditEntry $entry): void
    {
        AuditLog::create([
            'actor_user_id' => $this->resolveActorId($entry->actor),
            'action' => $entry->action->value,
            'subject_type' => $entry->subject?->getMorphClass(),
            'subject_id' => $entry->subject?->getKey(),
            'old_values' => $this->redact($entry->oldValues),
            'new_values' => $this->redact($entry->newValues),
            'context' => $this->redact($entry->context),
            'ip_address' => $this->ipAddress(),
            'user_agent' => $this->userAgent(),
            'request_id' => $this->requestId->has() ? $this->requestId->value() : null,
        ]);
    }

    public function recordAfterCommit(AuditEntry $entry): void
    {
        /*
         * Deliberately not DB::afterCommit().
         *
         * The Connection::afterCommit() method throws
         * RuntimeException('Transactions Manager has not been set.') unless the
         * `db.transactions` container binding exists -- and outside the testing
         * traits the framework never creates it. So calling it directly would
         * blow up in production and in every queue worker.
         *
         * This mirrors what Illuminate\Queue\Queue does: defer only when a
         * transaction manager is actually present, otherwise write now.
         */
        if (app()->bound('db.transactions')) {
            app('db.transactions')->addCallback(fn () => $this->record($entry));

            return;
        }

        $this->record($entry);
    }

    private function resolveActorId(?Authenticatable $actor): ?int
    {
        $identifier = ($actor ?? Auth::user())?->getAuthIdentifier();

        return is_numeric($identifier) ? (int) $identifier : null;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        return RedactSecrets::clean($values);
    }

    private function ipAddress(): ?string
    {
        return app()->runningInConsole() ? null : request()->ip();
    }

    private function userAgent(): ?string
    {
        return app()->runningInConsole()
            ? null
            : Str::limit((string) request()->userAgent(), 1000, '');
    }
}
