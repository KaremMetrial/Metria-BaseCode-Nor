<?php
namespace App\Contracts\Payments;
use App\Models\{Payment,PaymentRefund};
interface PaymentGatewayInterface {
 /** @return array{reference:string,client_secret:string} */
 public function create(Payment $payment): array;
 /** @return array{reference:string,status:string} status: succeeded, pending, failed */
 public function refund(Payment $payment,PaymentRefund $refund): array;
 /** Verified event: id, type, object. Invalid signatures must throw. */
 public function verifyWebhook(string $body,string $signature): array;
}
