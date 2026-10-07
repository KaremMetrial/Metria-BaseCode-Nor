<?php

namespace Tests\Feature\Localization;

use App\Support\Access\PermissionRegistry;
use App\Support\Access\RoleRegistry;
use Tests\TestCase;

class LocaleConfigurationTest extends TestCase
{
    public function test_translatable_locales_match_the_supported_application_locales(): void
    {
        // Astrotomic reads its own list from config/translatable.php. If the two
        // lists drift, the API happily accepts a locale that then cannot be
        // stored, and translations silently go missing. Enforcing it here turns
        // a convention into an invariant.
        $this->assertSame(
            array_keys(config('languages.supported')),
            config('translatable.locales'),
        );
    }

    public function test_default_locale_is_supported(): void
    {
        $this->assertContains(
            config('languages.default'),
            array_keys(config('languages.supported')),
        );
    }

    public function test_fallback_locale_is_supported(): void
    {
        $this->assertContains(
            config('languages.fallback'),
            array_keys(config('languages.supported')),
        );
    }

    public function test_every_locale_declares_direction_and_native_name(): void
    {
        foreach (config('languages.supported') as $locale => $meta) {
            $this->assertArrayHasKey('rtl', $meta, "Locale {$locale} must declare rtl");
            $this->assertArrayHasKey('native_name', $meta, "Locale {$locale} must declare native_name");
            $this->assertIsBool($meta['rtl']);
        }
    }

    public function test_astrotomic_translation_wrapper_is_configured(): void
    {
        // The { "translations": { "en": {...} } } admin payload shape only works
        // when this wrapper is a non-null string.
        $this->assertSame('translations', config('translatable.translations_wrapper'));
    }

    public function test_astrotomic_uses_missing_translations_fallback(): void
    {
        $this->assertTrue(config('translatable.use_fallback'));
    }

    public function test_permission_registry_has_no_duplicates(): void
    {
        $permissions = PermissionRegistry::all();

        $this->assertSame($permissions, array_values(array_unique($permissions)));
        $this->assertNotEmpty($permissions);
    }

    public function test_permissions_follow_the_resource_dot_action_convention(): void
    {
        foreach (PermissionRegistry::all() as $permission) {
            $this->assertMatchesRegularExpression(
                '/^[a-z]+\.[a-z]+$/',
                $permission,
                "Permission [{$permission}] must look like resource.action",
            );
        }
    }

    public function test_role_registry_only_grants_registered_permissions(): void
    {
        $registered = PermissionRegistry::all();

        foreach (RoleRegistry::roles() as $role => $permissions) {
            foreach ($permissions as $permission) {
                $this->assertContains($permission, $registered, "Role {$role} grants unregistered permission {$permission}");
            }
        }
    }
}
