<?php

namespace App\Contracts\Audit;

use App\DTOs\Audit\AuditEntry;

/**
 * Writes audit records.
 *
 * Abstracted because it has two real implementations already: the Eloquent
 * writer used in production, and a fake used in tests to assert "this action
 * was audited" without touching the database. It also keeps the door open for
 * shipping audit rows to an external sink later.
 */
interface AuditLoggerInterface
{
    /**
     * Write the audit row immediately.
     *
     * Inside a database transaction prefer `recordAfterCommit()`, so a rollback
     * cannot leave an audit row describing a change that never happened.
     */
    public function record(AuditEntry $entry): void;

    /**
     * Write the audit row once the surrounding transaction commits.
     *
     * Falls back to writing immediately when no transaction is open, so this is
     * always safe to call.
     */
    public function recordAfterCommit(AuditEntry $entry): void;
}
