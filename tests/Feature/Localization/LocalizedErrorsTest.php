<?php

namespace Tests\Feature\Localization;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Models\User;
use App\Services\Payments\MyFatoorahCatalog;
use Database\Seeders\RbacSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class LocalizedErrorsTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['en'])]
    #[TestWith(['ar'])]
    public function test_every_domain_error_returns_its_localized_message_and_stable_code(string $locale): void
    {
        Route::middleware('api')->get('/api/_test/domain/{code}', function (string $code): never {
            throw new DomainException(ErrorCode::from($code));
        });

        foreach (ErrorCode::cases() as $code) {
            $key = 'errors.'.$code->value;
            $this->assertTrue(Lang::hasForLocale($key, $locale), $key);
            $response = $this->getJson('/api/_test/domain/'.$code->value, ['X-Locale' => $locale]);
            $response->assertJsonPath('success', false)
                ->assertJsonPath('code', $code->value)
                ->assertJsonPath('message', trans($key, [], $locale))
                ->assertHeader('Content-Language', $locale);
            $this->assertGreaterThanOrEqual(400, $response->status());
        }
    }

    #[TestWith(['en'])]
    #[TestWith(['ar'])]
    public function test_framework_and_unexpected_failures_are_localized_without_leaking_exception_details(string $locale): void
    {
        $failures = [
            'authentication' => [new AuthenticationException('private credentials'), 401, 'UNAUTHENTICATED'],
            'authorization' => [new AuthorizationException('private permission'), 403, 'FORBIDDEN'],
            'throttle' => [new ThrottleRequestsException('private limiter', null, ['Retry-After' => 42]), 429, 'RATE_LIMITED'],
            'unexpected' => [new RuntimeException('private provider payload'), 500, 'INTERNAL_ERROR'],
        ];
        foreach ($failures as $name => [$exception, $status, $code]) {
            Route::middleware('api')->get('/api/_test/failure/'.$name, fn () => throw $exception);
            $response = $this->getJson('/api/_test/failure/'.$name, ['X-Locale' => $locale]);
            $response->assertStatus($status)->assertHeader('Content-Language', $locale)
                ->assertExactJson(['success' => false, 'code' => $code, 'message' => trans('errors.'.$code, [], $locale), 'errors' => []]);
            if ($name === 'throttle') {
                $response->assertHeader('Retry-After', '42');
            }
        }
        $this->getJson('/api/v1/missing-localized-route', ['X-Locale' => $locale])
            ->assertNotFound()->assertJsonPath('message', trans('errors.NOT_FOUND', [], $locale));
        $this->deleteJson('/api/v1/admin/auth/login', [], ['X-Locale' => $locale])
            ->assertStatus(405)->assertJsonPath('message', trans('errors.METHOD_NOT_ALLOWED', [], $locale));
    }

    #[TestWith(['en'])]
    #[TestWith(['ar'])]
    public function test_all_myfatoorah_endpoints_localize_invalid_fields_before_contacting_the_provider(string $locale): void
    {
        $this->signInAdmin();
        Http::preventStrayRequests();
        $attributes = trans('validation.attributes', [], $locale);

        foreach (app(MyFatoorahCatalog::class)->all() as $operation) {
            $payload = $this->invalidFields($operation['schema']);
            $payload['consent_reference'] = ['invalid'];
            $response = $this->json($operation['http_method'], '/api/v1/admin/integrations/myfatoorah/'.$operation['route'], $payload, ['X-Locale' => $locale, 'Idempotency-Key' => 'localization-test']);
            $response->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED')
                ->assertJsonPath('message', trans('errors.VALIDATION_FAILED', [], $locale))
                ->assertHeader('Content-Language', $locale);
            foreach ($response->json('errors') as $field => $messages) {
                $attribute = preg_replace('/\.\d+(?=\.|$)/', '.*', $field);
                $this->assertArrayHasKey($attribute, $attributes, $operation['name'].': '.$field);
                foreach ($messages as $message) {
                    $this->assertStringContainsString($attributes[$attribute], $message);
                    $this->assertStringNotContainsString('validation.', $message);
                    if ($locale === 'ar') {
                        $this->assertDoesNotMatchRegularExpression('/[a-zA-Z]/', $message);
                    }
                }
            }
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('my_fatoorah_operations', 0);
    }

    #[TestWith(['en'])]
    #[TestWith(['ar'])]
    public function test_custom_upload_and_unknown_field_errors_are_localized(string $locale): void
    {
        $this->signInAdmin();
        Http::preventStrayRequests();

        $this->postJson('/api/v1/admin/integrations/myfatoorah/suppliers/upload', [
            'SupplierCode' => 7, 'FileType' => 5, 'unexpected_secret' => 'hidden',
            'FileUpload' => ['FileName' => 'document.pdf', 'MediaType' => 'application/pdf', 'Buffer' => 'not-base64'],
        ], ['X-Locale' => $locale, 'Idempotency-Key' => 'invalid-upload'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.unexpected_secret.0', trans('validation.unexpected_field', [], $locale))
            ->assertJsonValidationErrors(['FileUpload.Buffer' => trans('validation.file', ['attribute' => $locale === 'ar' ? 'الملف المرفق' : 'file upload'], $locale)]);

        Http::assertNothingSent();
        $this->assertDatabaseCount('my_fatoorah_operations', 0);
    }

    #[TestWith(['en'])]
    #[TestWith(['ar'])]
    public function test_conditional_validation_translates_related_fields_and_values(string $locale): void
    {
        $this->signInAdmin();
        Http::preventStrayRequests();

        $this->patchJson('/api/v1/admin/integrations/myfatoorah/operations/1/resolution', ['outcome' => 'confirmed_succeeded', 'evidence' => 'Verified through merchant portal.'], ['X-Locale' => $locale])
            ->assertUnprocessable()
            ->assertJsonPath('errors.provider_reference.0', trans('validation.required_if', [
                'attribute' => $locale === 'ar' ? 'مرجع مزود الخدمة' : 'provider reference',
                'other' => $locale === 'ar' ? 'النتيجة المؤكدة' : 'confirmed outcome',
                'value' => $locale === 'ar' ? 'نجاح مؤكد' : 'confirmed success',
            ], $locale));

        Http::assertNothingSent();
    }

    #[TestWith(['en'])]
    #[TestWith(['ar'])]
    public function test_translation_objects_and_profile_fields_have_localized_errors(string $locale): void
    {
        $admin = $this->signInAdmin();
        $admin->givePermissionTo('categories.create');

        $this->postJson('/api/v1/admin/categories', ['translations' => ['ar' => []]], ['X-Locale' => $locale])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['translations.ar' => $locale === 'ar' ? 'يجب أن تتضمن كل ترجمة اسمًا.' : 'Each translation must include a name.'])
            ->assertJsonValidationErrors(['translations.en.name' => trans('validation.required', ['attribute' => $locale === 'ar' ? 'الاسم المترجم' : 'translated name'], $locale)]);

        $this->patchJson('/api/v1/admin/profile', ['roles' => ['super-admin']], ['X-Locale' => $locale])
            ->assertUnprocessable()->assertJsonPath('errors.roles.0', trans('validation.prohibited', ['attribute' => $locale === 'ar' ? 'الأدوار' : 'roles'], $locale));
    }

    private function signInAdmin(): User
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->givePermissionTo('myfatoorah.manage');
        Sanctum::actingAs($admin);

        return $admin;
    }

    #[TestWith(['en'])]
    #[TestWith(['ar'])]
    public function test_provider_failures_return_a_safe_localized_503(string $locale): void
    {
        $this->signInAdmin();
        config(['payments.providers.myfatoorah.secret' => 'test-api-key', 'payments.providers.myfatoorah.country' => 'KWT', 'payments.providers.myfatoorah.livemode' => false, 'myfatoorah.features' => ['multivendor']]);
        Http::preventStrayRequests();
        Http::fake(['https://apitest.myfatoorah.com/v2/GetSuppliers' => Http::response(['IsSuccess' => false, 'Message' => 'Private provider details'], 400)]);

        $this->getJson('/api/v1/admin/integrations/myfatoorah/suppliers/list', ['X-Locale' => $locale])
            ->assertServiceUnavailable()->assertHeader('Content-Language', $locale)
            ->assertExactJson(['success' => false, 'code' => 'PROVIDER_UNAVAILABLE', 'message' => trans('errors.PROVIDER_UNAVAILABLE', [], $locale), 'errors' => []]);

        Http::assertSentCount(1);
    }

    /**
     * @param  array{properties?: array<string, array<string, mixed>>}  $schema
     * @return array<string, mixed>
     */
    private function invalidFields(array $schema): array
    {
        $fields = [];
        foreach ($schema['properties'] ?? [] as $name => $property) {
            $fields[$name] = match ($property['type'] ?? 'string') {
                'object' => empty($property['properties']) ? 'invalid' : $this->invalidFields($property),
                'array' => [($property['items']['type'] ?? '') === 'object' ? $this->invalidFields($property['items']) : ['invalid']],
                'string' => ['invalid'],
                default => 'invalid',
            };
        }

        return $fields;
    }
}
