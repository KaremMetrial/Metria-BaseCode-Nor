<?php

namespace Tests\Feature\Localization;

use Illuminate\Support\Arr;
use Tests\TestCase;

class TranslationParityTest extends TestCase
{
    public function test_recursive_translation_keys_and_placeholders_match(): void
    {
        $en = $this->catalog('en');
        $ar = $this->catalog('ar');
        $this->assertSame(array_keys($en), array_keys($ar));
        foreach ($en as $key => $value) {
            $this->assertIsString($ar[$key], $key);
            $this->assertNotSame('', $ar[$key], $key);
            preg_match_all('/:[a-zA-Z_]+/', $value, $a);
            preg_match_all('/:[a-zA-Z_]+/', $ar[$key], $b);
            $this->assertEqualsCanonicalizing($a[0], $b[0], $key);
        }
    }

    private function catalog(string $locale): array
    {
        $keys = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(lang_path($locale), \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $prefix = substr($file->getPathname(), strlen(lang_path($locale)) + 1, -4);
                foreach (Arr::dot(require $file->getPathname()) as $k => $v) {
                    $keys[$prefix.'.'.$k] = $v;
                }
            }
        }
        ksort($keys);

        return $keys;
    }

    public function test_authentication_validation_and_not_found_are_localized(): void
    {
        foreach (['en', 'ar'] as $locale) {
            $this->postJson('/api/v1/admin/auth/login', [], ['Accept-Language' => $locale])->assertUnprocessable()->assertJsonPath('message', trans('errors.VALIDATION_FAILED', [], $locale))->assertJsonPath('errors.email.0', trans('validation.required', ['attribute' => trans('validation.attributes.email', [], $locale)], $locale));
            $this->getJson('/api/v1/no-such-route', ['Accept-Language' => $locale])->assertNotFound()->assertJsonPath('message',trans('errors.NOT_FOUND',[],$locale));
        }
    }
}
