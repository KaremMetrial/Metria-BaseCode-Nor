<?php

namespace App\Services\Operations;

use App\Models\MyFatoorahOperation;
use App\Models\NotificationDelivery;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

final class ReadinessChecks
{
    /** @return array<string, bool> No secrets or provider response bodies are returned. */
    public function run(bool $connections = true, bool $sandboxPayments = false, bool $deployment = false): array
    {
        $database = (string) config('database.default');
        $payment = config('payments.providers.'.config('payments.default'), []);
        $sms = config('sms.providers.'.config('sms.default'), []);
        $key = (string) config('app.key');
        $key = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        $checks = [
            'production_environment' => app()->isProduction(),
            'debug_disabled' => config('app.debug') === false,
            'https_url' => str_starts_with((string) config('app.url'), 'https://'),
            'application_key' => is_string($key) && strlen($key) === 32,
            'socket_secret' => strlen((string) config('realtime.secret')) >= 32,
            'mysql_database' => $database === 'mysql',
            'database_credentials' => ! in_array(config('database.connections.'.$database.'.password'), [null, '', 'root', 'password', 'metrial'], true) && config('database.connections.'.$database.'.username') !== 'root',
            'redis_queue' => config('queue.default') === 'redis',
            'redis_cache' => config('cache.default') === 'redis',
            'queue_retry_window' => (int) config('queue.connections.redis.retry_after') > 60,
            'payments_configured' => is_array($payment) && ! empty($payment['secret']) && ! empty($payment['webhook_secret']) && ($payment['livemode'] ?? null) === ! $sandboxPayments,
            'sms_configured' => is_array($sms) && ! empty($sms['account_sid']) && ! empty($sms['token']) && ! empty($sms['from']),
            'configuration_cached' => app()->configurationIsCached(),
            'routes_cached' => app()->routesAreCached(),
            'runtime_directories_writable' => is_writable(storage_path('logs')) && is_writable(storage_path('framework')) && is_writable(base_path('bootstrap/cache')),
        ];
        if (config('payments.default') === 'myfatoorah') {
            $currencies = ['KWT' => 'KWD', 'BHR' => 'BHD', 'OMN' => 'OMR', 'JOR' => 'JOD', 'SAU' => 'SAR', 'ARE' => 'AED', 'QAT' => 'QAR', 'EGY' => 'EGP'];
            $checks['payments_configured'] = $checks['payments_configured'] && isset($currencies[$payment['country'] ?? '']) && $currencies[$payment['country']] === ($payment['currency'] ?? null);
        }
        if (config('notifications.email_enabled')) {
            $checks['email_transport'] = ! in_array(config('mail.default'), ['log', 'array'], true);
        }
        if (config('notifications.fcm_enabled')) {
            $checks['fcm_credentials'] = is_file((string) config('notifications.fcm_credentials')) && ! empty(config('notifications.fcm_project'));
        }
        if ($connections) {
            try {
                DB::select('SELECT 1');
                $checks['database_reachable'] = true;
                if (! $deployment) {
                    $checks['no_uncertain_myfatoorah_operations'] = ! MyFatoorahOperation::query()->where('status', 'uncertain')->orWhere(fn ($query) => $query->where('status', 'sending')->where('updated_at', '<', now()->subMinutes(5)))->exists();
                    $checks['no_failed_jobs'] = DB::table('failed_jobs')->count() === 0;
                    $checks['outbox_not_stalled'] = ! UserNotification::query()->whereNull('published_at')->where('created_at', '<', now()->subMinutes(10))->exists();
                    $checks['channel_delivery_healthy'] = ! NotificationDelivery::query()->where('status', 'failed')->orWhere(fn ($q) => $q->where('status', 'pending')->where('created_at', '<', now()->subHour()))->exists();
                    $checks['no_aged_pending_refunds'] = ! PaymentRefund::query()->where('status', 'pending')->where('created_at', '<', now()->subDay())->exists();
                    $checks['no_aged_pending_payments'] = ! Payment::query()->whereIn('status', ['pending', 'processing'])->where('created_at', '<', now()->subDay())->exists();
                }
            } catch (\Throwable) {
                $checks['database_reachable'] = false;
            }
            try {
                Redis::connection()->ping();
                $checks['redis_reachable'] = true;
            } catch (\Throwable) {
                $checks['redis_reachable'] = false;
            }
        }

        return $checks;
    }
}
