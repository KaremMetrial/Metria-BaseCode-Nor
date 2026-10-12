<?php

namespace Database\Seeders;

use App\Models\MyFatoorahEntity;
use App\Models\MyFatoorahOperation;
use Illuminate\Support\Str;

/** Local snapshots only; these references do not exist at the external provider. */
final class DemoIntegrationSeeder extends DevelopmentSeeder
{
    protected function seed(): void
    {
        foreach (['invoice' => 'invoices.create', 'session' => 'sessions.create', 'subscription' => 'subscriptions.create',
            'supplier' => 'suppliers.create', 'refund' => 'refunds.create', 'shipment' => 'shipping.create', 'transfer' => 'transfers.create'] as $kind => $operation) {
            $reference = 'demo-'.$kind;
            MyFatoorahEntity::query()->firstOrCreate(['kind' => $kind, 'reference' => $reference], [
                'customer_id' => $this->user('client')->id, 'status' => 'demo', 'needs_refresh' => false,
                'snapshot' => ['demo' => true, 'reference' => $reference, 'note' => 'Local display fixture; no provider request was made.'], 'synced_at' => null,
            ]);
            MyFatoorahOperation::query()->firstOrCreate(['idempotency_key' => 'demo:myfatoorah:'.$kind], [
                'uuid' => (string) Str::uuid(), 'actor_id' => $this->user('admin')->id, 'customer_id' => $this->user('client')->id,
                'operation' => $operation, 'status' => 'succeeded', 'request_hash' => hash('sha256', 'demo:'.$operation),
                'result' => ['demo' => true, 'reference' => $reference, 'note' => 'Local display fixture; no provider request was made.'],
            ]);
        }
    }
}
