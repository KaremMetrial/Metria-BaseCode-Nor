<?php

namespace App\Actions\Wallet;

use App\Contracts\Audit\AuditLoggerInterface;
use App\DTOs\Audit\AuditEntry;
use App\Enums\AuditAction;
use App\Enums\WalletTransactionType;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;

final class AdjustWallet
{
    public function __construct(private readonly WalletService $wallets, private readonly AuditLoggerInterface $audit) {}

    public function execute(User $actor, Wallet $wallet, array $data): WalletTransaction
    {
        if (!$actor->isAdmin() || !$actor->isActive() || !$actor->can('wallets.adjust') || !in_array($data['direction'],['credit','debit'],true) || !$actor->can('wallets.'.$data['direction'])) {
            throw new \App\Exceptions\DomainException(\App\Enums\ErrorCode::FORBIDDEN);
        }
        return DB::transaction(function () use ($actor, $wallet, $data): WalletTransaction {
            $entry = $this->wallets->change($wallet, WalletTransactionType::from($data['direction']), (int) $data['amount'], 'admin:'.hash('sha256', $data['idempotency_key']), $data['reason']);
            if ($entry->wasRecentlyCreated) {
                $this->audit->record(new AuditEntry(AuditAction::WALLET_ADJUSTED, $entry, actor: $actor));
            }

            return $entry;
        }, 5);
    }
}
