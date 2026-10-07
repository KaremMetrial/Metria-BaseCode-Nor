<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Access\RoleRegistry;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scramble.cache.store' => 'array']);
    }

    public function test_every_api_operation_has_complete_documentation_and_correct_authentication(): void
    {
        Http::preventStrayRequests();
        $this->app['env'] = 'local';
        $document = $this->getJson('/docs/api.json')->assertOk()->json();
        $this->assertSame('3.1.0', $document['openapi']);
        $expected = [];
        $operationIds = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $path = '/'.substr($route->uri(), strlen('api/v1/'));
                $expected[] = strtolower($method).' '.$path;
                $operation = $document['paths'][$path][strtolower($method)] ?? null;
                $this->assertIsArray($operation, $method.' '.$path);
                $operationIds[] = $operation['operationId'];
                $this->assertNotEmpty($operation['summary']);
                $this->assertNotEmpty($operation['description']);
                $this->assertNotEmpty($operation['tags']);
                $success = array_filter(array_keys($operation['responses']), fn ($code): bool => $code >= 200 && $code < 300);
                $this->assertNotEmpty($success, $path);
                foreach ($operation['responses'] as $code => $response) {
                    $schema = $response['content']['application/json']['schema'];
                    $this->assertSame('object', $schema['type'], $path);
                    $this->assertSame([$code < 400], $schema['properties']['success']['enum'], $path);
                    $this->assertNotEmpty($response['description']);
                    $this->assertSame(['en', 'ar'], $response['headers']['Content-Language']['schema']['enum']);
                    if ($code >= 400) {
                        $this->assertSame(['success', 'code', 'message', 'errors'], $schema['required']);
                        $this->assertSame('object', $schema['properties']['errors']['type']);
                    }
                }
                $security = $operation['security'] ?? $document['security'];
                $authenticated = in_array('auth:sanctum', $route->gatherMiddleware(), true);
                $this->assertSame($authenticated, $security !== [], $path);
                if ($authenticated) {
                    $uri = preg_replace('/\{notification\}/', '11111111-1111-4111-8111-111111111111', $route->uri());
                    $uri = preg_replace('/\{[^}]+\}/', '1', $uri);
                    $this->json($method, '/'.$uri)->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED')->assertHeader('X-Request-ID');
                }
            }
        }
        $actual = [];
        foreach ($document['paths'] as $path => $methods) {
            foreach (array_keys($methods) as $method) {
                $actual[] = $method.' '.$path;
            }
        }
        $this->assertEqualsCanonicalizing($expected, $actual);
        $this->assertCount(count($operationIds), array_unique($operationIds));
        Http::assertNothingSent();
    }

    public function test_financial_headers_pagination_and_resource_shapes_match_the_contract(): void
    {
        $this->app['env'] = 'local';
        $document = $this->getJson('/docs/api.json')->assertOk()->json();
        foreach (['/client/payments', '/vendor/payments', '/admin/payments/{payment}/refunds', '/admin/wallets/{wallet}/adjustments'] as $path) {
            $operation = $document['paths'][$path]['post'];
            $header = collect($operation['parameters'])->firstWhere('name', 'Idempotency-Key');
            $this->assertSame('header', $header['in']);
            $this->assertTrue($header['required']);
            $this->assertSame(128, $header['schema']['maxLength']);
            $body = $this->resolve($document, $operation['requestBody']['content']['application/json']['schema']);
            $this->assertArrayNotHasKey('idempotency_key', $body['properties']);
            $this->assertContains('amount', $body['required']);
            $this->assertSame('integer', $body['properties']['amount']['type']);
        }
        foreach (['/categories', '/admin/categories', '/admin/users', '/admin/wallets', '/admin/payments', '/client/payments', '/vendor/payments', '/client/wallet/{wallet}/transactions', '/vendor/wallet/{wallet}/transactions'] as $path) {
            $operation = $document['paths'][$path]['get'];
            $data = $operation['responses']['200']['content']['application/json']['schema']['properties']['data'];
            $this->assertSame('array', $data['properties']['items']['type'], $path);
            $this->assertArrayHasKey('$ref', $data['properties']['items']['items'], $path);
            $this->assertSame(['current_page', 'last_page', 'per_page', 'total'], $data['properties']['meta']['required']);
            $this->assertNotNull(collect($operation['parameters'])->firstWhere('name', 'page'));
        }
        foreach (['CategoryResource', 'AdminCountryResource', 'AdminGovernorateResource', 'AdminCityResource'] as $resource) {
            $translation = $document['components']['schemas'][$resource]['properties']['translations'];
            $this->assertSame('object', $translation['type']);
            $this->assertArrayHasKey('name', $translation['additionalProperties']['properties']);
        }
        $payment = $document['components']['schemas']['PaymentResource'];
        $this->assertNotContains('client_secret', $payment['required']);
        $this->assertContains('pending', $payment['properties']['status']['enum']);
        $this->assertSame('date-time', $payment['properties']['created_at']['format']);
    }

    public function test_translation_create_and_patch_requirements_are_distinct(): void
    {
        $this->app['env'] = 'local';
        $document = $this->getJson('/docs/api.json')->assertOk()->json();
        foreach (['categories' => 'category', 'locations/countries' => 'country', 'locations/governorates' => 'governorate', 'locations/cities' => 'city'] as $resource => $parameter) {
            $create = $this->resolve($document, $document['paths']['/admin/'.$resource]['post']['requestBody']['content']['application/json']['schema']);
            $update = $this->resolve($document, $document['paths']['/admin/'.$resource.'/{'.$parameter.'}']['patch']['requestBody']['content']['application/json']['schema']);
            $this->assertContains('translations', $create['required']);
            $this->assertSame(['en'], $create['properties']['translations']['required']);
            $this->assertNotContains('translations', $update['required'] ?? []);
            $this->assertSame([], $update['properties']['translations']['required'] ?? []);
            foreach ([$create, $update] as $body) {
                foreach (['en', 'ar'] as $locale) {
                    $this->assertSame(['name'], $body['properties']['translations']['properties'][$locale]['required']);
                }
            }
        }
    }

    public function test_webhook_is_public_but_requires_the_provider_signature_and_documents_size_failures(): void
    {
        $this->app['env'] = 'local';
        $document = $this->getJson('/docs/api.json')->assertOk()->json();
        $operation = $document['paths']['/webhooks/payments/{provider}']['post'];
        $this->assertSame([], $operation['security']);
        $this->assertStringContainsString('Required for Stripe', collect($operation['parameters'])->firstWhere('name', 'Stripe-Signature')['description']);
        $this->assertStringContainsString('Required for MyFatoorah', collect($operation['parameters'])->firstWhere('name', 'MyFatoorah-Signature')['description']);
        $variants = $operation['requestBody']['content']['application/json']['schema']['anyOf'];
        $this->assertSame(['id', 'type', 'livemode', 'data'], $variants[0]['required']);
        $this->assertSame(['Event', 'Data'], $variants[1]['required']);
        $this->assertArrayHasKey('400', $operation['responses']);
        $this->assertArrayHasKey('413', $operation['responses']);
        $this->assertSame(['INTERNAL_ERROR'], $operation['responses']['413']['content']['application/json']['schema']['properties']['code']['enum']);
    }

    public function test_documentation_ui_is_available_in_development_but_not_to_deployed_guests(): void
    {
        foreach (['local', 'development'] as $environment) {
            $this->app['env'] = $environment;
            $this->get('/docs/api')->assertOk()->assertSee('Metrial API v1');
        }
        foreach (['staging', 'production'] as $environment) {
            $this->app['env'] = $environment;
            $this->get('/docs/api')->assertForbidden();
            $this->getJson('/docs/api.json')->assertForbidden();
        }
    }

    public function test_deployed_documentation_requires_an_active_super_admin_bearer_token(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleRegistry::SUPER_ADMIN);
        $token = $admin->createToken('docs')->plainTextToken;
        $this->app['env'] = 'production';
        $this->withToken($token)->get('/docs/api')->assertOk();
        $this->withToken($token)->getJson('/docs/api.json')->assertOk();

        $admin->forceFill(['status' => UserStatus::BLOCKED])->save();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/docs/api.json')->assertForbidden();

        $client = User::factory()->create();
        $client->assignRole(RoleRegistry::SUPER_ADMIN);
        $this->app['auth']->forgetGuards();
        $this->withToken($client->createToken('docs')->plainTextToken)->getJson('/docs/api.json')->assertForbidden();

        $ordinaryAdmin = User::factory()->admin()->create();
        $this->app['auth']->forgetGuards();
        $this->withToken($ordinaryAdmin->createToken('docs')->plainTextToken)->getJson('/docs/api.json')->assertForbidden();
    }

    public function test_openapi_export_has_no_unknown_schema_types(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'metrial-openapi-');
        try {
            $this->artisan('scramble:export', ['--path' => $path, '--fail-on-unknown' => true])->assertSuccessful();
            $this->assertNotEmpty(json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR)['paths']);
        } finally {
            unlink($path);
        }
    }

    /** @param array<string, mixed> $document
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function resolve(array $document, array $schema): array
    {
        return isset($schema['$ref']) ? $document['components']['schemas'][basename($schema['$ref'])] : $schema;
    }

    public function test_myfatoorah_workflows_have_distinct_requests_and_realistic_examples(): void
    {
        $this->app['env'] = 'local';
        $document = $this->getJson('/docs/api.json')->assertOk()->json();
        $base = '/admin/integrations/myfatoorah/';
        $subscription = $document['paths'][$base.'subscriptions/create']['post'];
        $body = $subscription['requestBody']['content']['application/json']['schema'];
        $this->assertContains('consent_reference', $body['required']);
        $this->assertContains('RecurringModel', $body['required']);
        $this->assertSame(10, $body['examples'][0]['InvoiceValue']);
        $this->assertSame('Monthly', $body['examples'][0]['RecurringModel']['RecurringType']);
        $this->assertSame(1, $body['properties']['RecurringModel']['properties']['IntervalDays']['minimum']);
        $this->assertSame(180, $body['properties']['RecurringModel']['properties']['IntervalDays']['maximum']);
        $this->assertTrue(collect($subscription['parameters'])->firstWhere('name', 'Idempotency-Key')['required']);
        $invoice = $document['paths'][$base.'invoices/create']['post']['requestBody']['content']['application/json']['schema'];
        $this->assertArrayNotHasKey('Card', $invoice['properties']['SourceOfFund']['properties']);
        $this->assertArrayNotHasKey('idempotency_key', $invoice['properties']);
        $supplier = $document['paths'][$base.'suppliers/get']['get'];
        $this->assertArrayNotHasKey('requestBody', $supplier);
        $this->assertTrue(collect($supplier['parameters'])->firstWhere('name', 'SupplierCode')['required']);
        $this->assertArrayHasKey('post', $document['paths'][$base.'shipping/pickup']);
        $this->assertArrayNotHasKey('get', $document['paths'][$base.'shipping/pickup']);
    }
}
