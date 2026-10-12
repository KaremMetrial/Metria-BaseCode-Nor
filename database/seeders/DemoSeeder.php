<?php

namespace Database\Seeders;

/** Run with php artisan db:seed --class=DemoSeeder. Reference-only seeding stays unchanged. */
final class DemoSeeder extends DevelopmentSeeder
{
    protected function seed(): void
    {
        $this->call([
            DatabaseSeeder::class,
            DemoContentSeeder::class,
            DemoUserSeeder::class,
            DemoSupportSeeder::class,
            DemoFinanceSeeder::class,
            DemoNotificationSeeder::class,
            DemoReferralSeeder::class,
            DemoMediaSeeder::class,
            DemoIntegrationSeeder::class,
        ]);
    }
}
