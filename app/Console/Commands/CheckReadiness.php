<?php

namespace App\Console\Commands;

use App\Services\Operations\ReadinessChecks;
use Illuminate\Console\Command;

final class CheckReadiness extends Command
{
    protected $signature = 'app:readiness {--configuration-only : Skip database and Redis probes} {--sandbox-payments : Require sandbox payment mode for staging}';

    protected $description = 'Check production configuration and operational backlog without exposing secrets';

    public function handle(ReadinessChecks $checks): int
    {
        $results = $checks->run(! $this->option('configuration-only'), (bool) $this->option('sandbox-payments'));
        $this->line(json_encode(['ready' => ! in_array(false, $results, true), 'checks' => $results, 'external_provider_delivery_verified' => false], JSON_THROW_ON_ERROR));

        return in_array(false, $results, true) ? self::FAILURE : self::SUCCESS;
    }
}
