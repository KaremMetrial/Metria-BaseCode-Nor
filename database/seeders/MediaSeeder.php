<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class MediaSeeder extends Seeder
{
    /** Register existing avatars; never seed fabricated upload records. */
    public function run(): void
    {
        Artisan::call('media:import-avatars');
    }
}
