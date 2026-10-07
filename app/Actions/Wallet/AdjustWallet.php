<?php
namespace App\Actions\Wallet;
use App\Contracts\Audit\AuditLoggerInterface;
use App\DTOs\Audit\AuditEntry;
use App\Enums\{AuditAction,WalletTransactionType};
use App\Models\{Wallet,WalletTransaction,User};
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
final class AdjustWallet {
 public function __construct(private readonly WalletService $wallets,private readonly AuditLoggerInterface $audit) {}
 public function execute(User $actor,Wallet $wallet,array $data): WalletTransaction {
  return DB::transaction(function() use($actor,$wallet,$data): WalletTransaction {
   $entry=$this->wallets->change($wallet,WalletTransactionType::from($data['direction']),(int)$data['amount'],'admin:'.$data['idempotency_key'],$data['reason']);
   if($entry->wasRecentlyCreated) { $this->audit->record(new AuditEntry(AuditAction::WALLET_ADJUSTED,$entry,actor:$actor)); }
   return $entry;
  },5);
 }
}
