<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:reconcile-payments')]
#[Description('Command description')]
class ReconcilePayments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
    }
}
