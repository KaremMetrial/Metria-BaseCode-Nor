<?php

namespace Database\Seeders;

use App\Models\ContactSetting;
use Illuminate\Database\Seeder;

class ContactSettingSeeder extends Seeder
{
    public function run(): void
    {
        if (! ContactSetting::query()->find(1)) {
            $setting = new ContactSetting;
            $setting->forceFill(['id' => 1, 'settings' => []])->save();
        }
    }
}
