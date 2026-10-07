<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function checks(): array
    {
        return [
            'wallets' => ['balance >= 0 AND balance <= 9000000000000000'],
            'wallet_transactions' => ["amount > 0 AND amount <= 99999999 AND balance_after >= 0 AND direction IN ('credit','debit')"],
            'payments' => ["amount > 0 AND amount <= 99999999 AND refunded_amount >= 0 AND refunded_amount <= amount AND status IN ('pending','processing','paid','failed','partially_refunded','refunded')"],
            'payment_refunds' => ["amount > 0 AND amount <= 99999999 AND status IN ('pending','succeeded','failed')"],
        ];
    }

    public function up(): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';
        foreach ($this->checks() as $table => $checks) {
            $condition = $checks[0];
            if ($sqlite) {
                $condition = preg_replace('/\\b(balance|amount|balance_after|direction|refunded_amount|status)\\b/', 'NEW.$1', $condition);
                foreach (['INSERT', 'UPDATE'] as $event) {
                    DB::unprepared("CREATE TRIGGER {$table}_check_{$event} BEFORE {$event} ON {$table} WHEN NOT ({$condition}) BEGIN SELECT RAISE(ABORT, 'financial_invariant'); END");
                }
            } else {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_amount_check CHECK ({$condition})");
            }
        }
        foreach (['UPDATE', 'DELETE'] as $event) {
            $sql = $sqlite ? "CREATE TRIGGER wallet_ledger_{$event} BEFORE {$event} ON wallet_transactions BEGIN SELECT RAISE(ABORT, 'immutable_ledger'); END" : "CREATE TRIGGER wallet_ledger_{$event} BEFORE {$event} ON wallet_transactions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable_ledger'";
            DB::unprepared($sql);
        }
    }

    public function down(): void
    {
        foreach (['UPDATE', 'DELETE'] as $event) {
            DB::unprepared("DROP TRIGGER IF EXISTS wallet_ledger_{$event}");
        }
        foreach (array_keys($this->checks()) as $table) {
            if (DB::getDriverName() === 'sqlite') {
                foreach (['INSERT', 'UPDATE'] as $event) {
                    DB::unprepared("DROP TRIGGER IF EXISTS {$table}_check_{$event}");
                }
            } else {
                DB::statement("ALTER TABLE {$table} DROP CHECK {$table}_amount_check");
            }
        }
    }
};
