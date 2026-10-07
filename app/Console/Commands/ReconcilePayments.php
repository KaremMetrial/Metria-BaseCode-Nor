<?php

namespace App\Console\Commands;

use App\Actions\Payments\ReconcilePayment;
use App\Exceptions\DomainException;
use App\Models\Payment;
use App\Models\PaymentRefund;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

final class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile {--payment= : Local payment ID} {--limit=25 : Maximum payments, 1-100}';

    protected $description = 'Recover pending financial outcomes from authenticated provider reads; never initiates money movement';

    public function handle(ReconcilePayment $action): int
    {
        $limit = (string) $this->option('limit');
        $id = $this->option('payment');
        if (! ctype_digit($limit) || (int) $limit < 1 || (int) $limit > 100 || ($id !== null && (! ctype_digit((string) $id) || (int) $id < 1))) {
            $this->error('INVALID_OPTIONS');

            return self::INVALID;
        }
        $query = Payment::query()->where(function (Builder $query): void {
            $query->whereIn('status', ['pending', 'processing'])->orWhereIn('id', PaymentRefund::query()->select('payment_id')->where('status', 'pending'));
        });
        if ($id !== null) {
            $query->whereKey((int) $id);
        }
        $failed = false;
        foreach ($query->orderBy('updated_at')->orderBy('id')->limit((int) $limit)->get() as $payment) {
            try {
                $result = $action->execute($payment);
                $this->line(json_encode($result, JSON_THROW_ON_ERROR));
                $failed = $failed || $result['unresolved'] > 0;
            } catch (\Throwable $exception) {
                $this->error(json_encode(['payment_id' => $payment->id, 'code' => $exception instanceof DomainException ? $exception->errorCode()->value : 'INTERNAL_ERROR'], JSON_THROW_ON_ERROR));
                $failed = true;
            } finally {
                // Rotate unresolved rows so one stale page cannot starve later work.
                Payment::query()->whereKey($payment->id)->update(['updated_at' => now()]);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
