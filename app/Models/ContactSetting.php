<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @property array<string, mixed> $settings */
class ContactSetting extends Model
{
    protected function casts(): array
    {
        return ['settings' => 'array', 'version' => 'integer'];
    }

    /** @return array<string, mixed> */
    public function publicSettings(): array
    {
        $settings = $this->settings;
        $translations = $settings['translations'] ?? [];
        unset($settings['translations']);

        return array_replace(['phone' => null, 'whatsapp' => null, 'social_links' => []], $settings, $translations[app()->getLocale()] ?? $translations[config('languages.default')] ?? ['address' => null, 'working_hours' => null, 'intro' => null]);
    }
}
