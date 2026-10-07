<?php

use App\Actions\Auth\VerifyPhoneLogin;
use App\Actions\Payments\ProcessWebhook;
use App\Actions\Payments\RefundPayment;
use App\Enums\UserType;
use App\Enums\WalletTransactionType;
use App\Exceptions\DomainException;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Payments\StripeGateway;
use App\Services\Wallet\WalletService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\RecordingSms;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$data = json_decode(base64_decode($argv[1]), true, 512, JSON_THROW_ON_ERROR);
config(['sms.default' => 'test', 'sms.providers.test' => ['driver' => RecordingSms::class], 'payments.providers.stripe' => ['driver' => StripeGateway::class, 'secret' => 'concurrency-secret', 'webhook_secret' => 'concurrency-test', 'livemode' => false]]);
touch($data['barrier'].'.'.$data['worker']);
$deadline = microtime(true) + 15;
while (! is_file($data['barrier'].'.go')) {
    if (microtime(true) > $deadline) {
        exit(2);
    }
    usleep(10000);
}
Http::fake(['https://api.stripe.com/v1/refunds' => fn ($request) => Http::response(['id' => 're_'.$request['metadata']['refund_uuid'], 'status' => 'succeeded'])]);
try {
    match ($data['operation']) {
        'refund' => app(RefundPayment::class)->execute(User::findOrFail($data['actor']), Payment::findOrFail($data['payment']), 80, $data['key']),
        'debit' => app(WalletService::class)->change(Wallet::findOrFail($data['wallet']), WalletTransactionType::DEBIT, 80, $data['key'], 'concurrent'),
        'otp' => app(VerifyPhoneLogin::class)->execute($data['input'], UserType::CLIENT),
        'webhook' => app(ProcessWebhook::class)->execute('stripe', $data['body'], ['stripe-signature' => [$data['signature']]]),
    };
    echo json_encode(['success' => true]);
} catch (DomainException $e) {
    echo json_encode(['success' => false, 'code' => $e->errorCode()->value]);
}
