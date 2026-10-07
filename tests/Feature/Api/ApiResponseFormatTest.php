<?php

namespace Tests\Feature\Api;

use App\Enums\ErrorCode;
use App\Support\ApiResponse;
use Tests\TestCase;

class ApiResponseFormatTest extends TestCase
{
    public function test_success_envelope_shape(): void
    {
        $response = ApiResponse::success(['id' => 1], 'Created.');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'success' => true,
            'message' => 'Created.',
            'data' => ['id' => 1],
        ], $response->getData(true));
    }

    public function test_error_envelope_shape(): void
    {
        $response = ApiResponse::error(ErrorCode::PHONE_ALREADY_EXISTS);
        $payload = $response->getData(true);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertFalse($payload['success']);
        $this->assertSame('PHONE_ALREADY_EXISTS', $payload['code']);
        $this->assertSame(__('errors.PHONE_ALREADY_EXISTS'), $payload['message']);
        $this->assertSame([], $payload['errors']);
    }

    public function test_empty_errors_serialise_as_an_object_not_an_array(): void
    {
        // Clients parse this field as a map; `[]` would be a type change.
        $this->assertStringContainsString(
            '"errors":{}',
            ApiResponse::error(ErrorCode::NOT_FOUND)->getContent(),
        );
    }

    public function test_field_errors_are_preserved(): void
    {
        $payload = ApiResponse::error(
            ErrorCode::VALIDATION_FAILED,
            errors: ['phone' => ['The phone number is not valid.']],
        )->getData(true);

        $this->assertSame(['phone' => ['The phone number is not valid.']], $payload['errors']);
    }

    public function test_message_is_localised_while_code_is_not(): void
    {
        app()->setLocale('ar');

        $payload = ApiResponse::error(ErrorCode::INVALID_PHONE_NUMBER)->getData(true);

        $this->assertSame('INVALID_PHONE_NUMBER', $payload['code'], 'The code is a stable contract value');
        $this->assertSame(trans('errors.INVALID_PHONE_NUMBER', [], 'ar'), $payload['message']);
        $this->assertNotSame(trans('errors.INVALID_PHONE_NUMBER', [], 'en'), $payload['message']);
    }

    public function test_every_error_code_is_translated_in_every_supported_locale(): void
    {
        foreach (array_keys(config('languages.supported')) as $locale) {
            foreach (ErrorCode::cases() as $code) {
                $key = "errors.{$code->value}";

                $this->assertNotSame(
                    $key,
                    trans($key, [], $locale),
                    "Missing [{$locale}] translation for error code {$key}",
                );
            }
        }
    }

    public function test_status_codes_are_canonical(): void
    {
        $expected = [
            ErrorCode::UNAUTHENTICATED->value => 401,
            ErrorCode::INVALID_CREDENTIALS->value => 401,
            ErrorCode::FORBIDDEN->value => 403,
            ErrorCode::ACCOUNT_DISABLED->value => 403,
            ErrorCode::NOT_FOUND->value => 404,
            ErrorCode::METHOD_NOT_ALLOWED->value => 405,
            ErrorCode::PHONE_ALREADY_EXISTS->value => 409,
            ErrorCode::VALIDATION_FAILED->value => 422,
            ErrorCode::RATE_LIMITED->value => 429,
            ErrorCode::INTERNAL_ERROR->value => 500,
        ];

        foreach ($expected as $value => $status) {
            $code = ErrorCode::from($value);

            $this->assertSame($status, ApiResponse::defaultStatus($code), "Wrong status for {$value}");
        }
    }
}
