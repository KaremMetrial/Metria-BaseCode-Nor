<?php

namespace Tests\Feature\Audit;

use App\Contracts\Audit\AuditLoggerInterface;
use App\DTOs\Audit\AuditEntry;
use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\RequestId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_an_audit_row(): void
    {
        $actor = User::factory()->admin()->withoutPhone()->create();
        $subject = User::factory()->client()->create();

        Sanctum::actingAs($actor);

        $this->audit()->record(new AuditEntry(
            action: AuditAction::USER_STATUS_CHANGED,
            subject: $subject,
            oldValues: ['status' => 'active'],
            newValues: ['status' => 'blocked'],
        ));

        $log = AuditLog::query()->firstOrFail();

        $this->assertSame(AuditAction::USER_STATUS_CHANGED, $log->action);
        $this->assertSame($actor->getKey(), $log->actor_user_id);
        $this->assertSame($subject->getMorphClass(), $log->subject_type);
        $this->assertSame($subject->getKey(), $log->subject_id);
        $this->assertSame(['status' => 'active'], $log->old_values);
        $this->assertSame(['status' => 'blocked'], $log->new_values);
    }

    public function test_an_explicit_actor_overrides_the_authenticated_user(): void
    {
        $explicit = User::factory()->admin()->withoutPhone()->create();
        Sanctum::actingAs(User::factory()->admin()->withoutPhone()->create());

        $this->audit()->record(new AuditEntry(
            action: AuditAction::WALLET_ADJUSTED,
            actor: $explicit,
        ));

        $this->assertSame($explicit->getKey(), AuditLog::query()->firstOrFail()->actor_user_id);
    }

    public function test_secrets_are_redacted_before_storage(): void
    {
        $this->audit()->record(new AuditEntry(
            action: AuditAction::USER_PHONE_CHANGED,
            oldValues: ['password' => 'super-secret', 'phone' => '+201012345678'],
            newValues: [
                'otp' => '123456',
                'remember_token' => 'abc123',
                'nested' => ['access_token' => 'tok_live_xyz', 'note' => 'kept'],
            ],
        ));

        $log = AuditLog::query()->firstOrFail();

        $this->assertSame('[REDACTED]', $log->old_values['password']);
        $this->assertSame('[REDACTED]', $log->new_values['otp']);
        $this->assertSame('[REDACTED]', $log->new_values['remember_token']);
        $this->assertSame('[REDACTED]', $log->new_values['nested']['access_token']);

        // Non-secret values must survive: over-redaction makes an audit trail
        // worthless, which is why the key list is narrow.
        $this->assertSame('[REDACTED]', $log->old_values['phone']);
        $this->assertSame('kept', $log->new_values['nested']['note']);
    }

    public function test_request_metadata_and_correlation_id_are_captured(): void
    {
        $requestId = app(RequestId::class);
        $requestId->set('test-request-id-1234');

        $this->audit()->record(new AuditEntry(action: AuditAction::USER_LOGIN));

        $log = AuditLog::query()->firstOrFail();

        $this->assertSame('test-request-id-1234', $log->request_id);
    }

    public function test_a_missing_correlation_id_is_stored_as_null_not_generated(): void
    {
        // A queue worker has no request, so inventing an id would create a
        // misleading correlation value.
        $this->audit()->record(new AuditEntry(action: AuditAction::USER_LOGIN));

        $this->assertNull(AuditLog::query()->firstOrFail()->request_id);
    }

    public function test_record_writes_immediately(): void
    {
        $this->audit()->record(new AuditEntry(action: AuditAction::USER_LOGIN));

        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_record_after_commit_defers_until_the_transaction_commits(): void
    {
        DB::transaction(function (): void {
            $this->audit()->recordAfterCommit(new AuditEntry(action: AuditAction::USER_LOGIN));

            $this->assertDatabaseCount('audit_logs', 0);
        });

        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_record_after_commit_still_writes_when_there_is_no_transaction(): void
    {
        $this->audit()->recordAfterCommit(new AuditEntry(action: AuditAction::USER_LOGIN));

        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_a_rolled_back_transaction_leaves_no_audit_row(): void
    {
        try {
            DB::transaction(function (): void {
                $this->audit()->recordAfterCommit(new AuditEntry(action: AuditAction::USER_LOGIN));

                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function audit(): AuditLoggerInterface
    {
        return app(AuditLoggerInterface::class);
    }
}
