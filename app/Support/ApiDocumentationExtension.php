<?php

namespace App\Support;

use App\Enums\ErrorCode;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

final class ApiDocumentationExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $route = $routeInfo->route;
        $segments = explode('/', $route->uri());
        $actor = in_array($segments[2], ['admin', 'client', 'vendor'], true) ? ucfirst($segments[2]) : 'Public';
        $area = $actor === 'Public' ? $segments[2] : $segments[3];
        $operation->setTags([$area === 'webhooks' ? 'Payment webhooks' : $actor.' · '.Str::headline($area)]);
        $middleware = $route->gatherMiddleware();
        $authenticated = in_array('auth:sanctum', $middleware, true);
        $notes = [$operation->description];
        if ($authenticated) {
            $notes[] = "Requires a bearer token for the {$actor} actor.";
        }
        if (in_array('active', $middleware, true)) {
            $notes[] = 'The account must be active; pending or disabled accounts receive 403.';
        }
        foreach ($middleware as $name) {
            if (str_starts_with($name, 'can:')) {
                $notes[] = 'Required permission: `'.substr($name, 4).'`.';
            }
            if (str_starts_with($name, 'throttle:')) {
                $notes[] = match (substr($name, 9)) {
                    'api' => 'Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.',
                    'login' => 'Additional rate limit: 5 requests/minute per IP.',
                    'otp_request' => 'OTP request rate limit: 3 requests/minute per IP.',
                    'otp_verify' => 'OTP verification rate limit: 5 requests/minute per IP.',
                    'webhooks' => 'Webhook rate limit: 300 requests/minute per IP.',
                    default => '',
                };
            }
        }
        $operation->description(implode("\n\n", array_filter($notes)));
        $operation->addParameters([
            Parameter::make('X-Request-ID', 'header')->setSchema(Schema::fromType((new StringType)->pattern('^[A-Za-z0-9._-]{8,64}$')))
                ->description('Optional correlation ID. Invalid or missing values are replaced. Returned on every response.'),
            Parameter::make('X-Locale', 'header')->setSchema(Schema::fromType((new StringType)->enum(['en', 'ar'])))
                ->description('Response language. Precedence: locale query, X-Locale, user preference, Accept-Language, then en.'),
            Parameter::make('locale', 'query')->setSchema(Schema::fromType((new StringType)->enum(['en', 'ar'])))
                ->description('Overrides the response language for this request.'),
        ]);
        $this->documentRequest($operation);
        $this->documentResponses($operation, $authenticated);
        if ($area === 'webhooks') {
            $this->documentWebhook($operation);
        }
    }

    private function documentRequest(Operation $operation): void
    {
        foreach ($operation->parameters as $parameter) {
            if ($parameter instanceof Parameter && $parameter->schema?->type->enum && $parameter->schema->type instanceof UnknownType) {
                $parameter->schema = Schema::fromType((new StringType)->addProperties($parameter->schema->type));
            }
            if ($parameter instanceof Parameter && $parameter->name === 'notification') {
                $parameter->schema = Schema::fromType((new StringType)->format('uuid'));
            }
        }

        $schema = $this->resolveSchema($operation->requestBodyObject?->content['application/json'] ?? null);
        if (! $schema instanceof Schema || ! $schema->type instanceof ObjectType) {
            return;
        }
        $body = $schema->type;
        if ($body->hasProperty('translations')) {
            if ($operation->method === 'patch') {
                if (str_contains($operation->path, '/categories/')) {
                    $body = $body->clone();
                    $operation->requestBodyObject->setContent('application/json', Schema::fromType($body));
                }
                $body->required = array_values(array_diff($body->required, ['translations']));
                $operation->requestBodyObject->required(false);
            }
            $translations = $body->getProperty('translations');
            if ($translations instanceof ObjectType) {
                $translations->setRequired($operation->method === 'post' ? [config('languages.default')] : []);
                foreach ($translations->properties as $translation) {
                    if ($translation instanceof ObjectType) {
                        $translation->setRequired(['name']);
                    }
                }
            }
        }
        foreach ($body->properties as $name => $property) {
            if ($property?->enum && $property instanceof UnknownType) {
                $body->properties[$name] = (new StringType)->addProperties($property);
            }
        }
        if ($operation->method === 'post' && Str::endsWith($operation->path, ['/payments', '/refunds', '/adjustments'])) {
            $operation->addParameters([
                Parameter::make('Idempotency-Key', 'header')->required(true)
                    ->setSchema(Schema::fromType((new StringType)->setMin(1)->setMax(128)->pattern('^[A-Za-z0-9._:-]+$')))
                    ->description('Required unique key, 1–128 characters. Retry the same operation with the same key and payload; conflicting reuse returns 409.'),
            ]);
            unset($body->properties['idempotency_key']);
            $body->required = array_values(array_diff($body->required, ['idempotency_key']));
        }
        if ($body->hasProperty('amount')) {
            $body->getProperty('amount')->setDescription('Positive integer in the currency’s smallest unit (100 = 1.00 for EGP/USD; 1000 = 1.000 for KWD/BHD/OMR/JOD).');
        }
        if (str_ends_with($operation->path, '/payments') && $operation->method === 'post') {
            $body->required = array_values(array_diff($body->required, ['provider']));
            if (in_array(config('payments.default'), $body->getProperty('provider')->enum, true)) {
                $body->getProperty('provider')->default(config('payments.default'));
            }
            $body->getProperty('provider')->setDescription('May be omitted only when an enabled default gateway is configured.');
            $this->omitProhibited($body, ['status', 'user_id', 'wallet_id']);
        }
        if (str_ends_with($operation->path, '/profile') && $operation->method === 'patch') {
            $this->omitProhibited($body, ['phone', 'phone_country_id', 'type', 'status', 'roles', 'permissions', 'balance', 'phone_verified_at', 'password', 'email']);
            $body->addProperty('timezone', (new StringType)->nullable(true)->examples(['Africa/Cairo'])->setDescription('IANA timezone identifier, or null.'));
        }
        if ($body->hasProperty('password')) {
            $body->getProperty('password')->format('password');
        }
        if ($body->hasProperty('challenge_id')) {
            $body->getProperty('challenge_id')->format('uuid');
        }
    }

    private function resolveSchema(Schema|Reference|null $schema): ?Schema
    {
        $schema = $schema instanceof Reference ? $schema->resolve() : $schema;

        return $schema instanceof Schema ? $schema : null;
    }

    /** @param list<string> $fields */
    private function omitProhibited(ObjectType $body, array $fields): void
    {
        foreach ($fields as $field) {
            unset($body->properties[$field]);
        }
        $body->setDescription('Do not send these prohibited fields: '.implode(', ', $fields).'.');
    }

    private function documentResponses(Operation $operation, bool $authenticated): void
    {
        $statuses = [422, 429, 500];
        if ($authenticated) {
            $statuses = [...$statuses, 401, 403];
        }
        if (str_contains($operation->path, '{')) {
            $statuses[] = 404;
        }
        if (in_array($operation->method, ['post', 'patch', 'delete'], true)) {
            $statuses[] = 409;
        }
        if (Str::contains($operation->path, ['/otp/', '/phone/request', '/payments', '/realtime/token', '/devices', '/integrations/myfatoorah'])) {
            $statuses[] = 503;
        }
        if (str_ends_with($operation->path, '/auth/login')) {
            $statuses[] = 401;
        }
        $responses = [];
        foreach ($operation->responses ?? [] as $response) {
            $response = $response instanceof Reference ? $response->resolve() : $response;
            if (! $response instanceof Response) {
                continue;
            }
            if ((int) $response->code >= 400) {
                $statuses[] = (int) $response->code;

                continue;
            }
            $response->setDescription($response->code === 201 ? 'Created successfully.' : 'Completed successfully.');
            $schema = $this->resolveSchema($response->content['application/json'] ?? null);
            if ($schema instanceof Schema && $schema->type instanceof ObjectType) {
                $envelope = $schema->type;
                $envelope->addProperty('success', (new BooleanType)->enum([true]));
                $envelope->addProperty('message', (new StringType)->nullable(true)->setDescription('Localized human-readable message, or null. Use error codes for program logic.'));
                $data = $envelope->properties['data'] ?? null;
                if ($data instanceof ObjectType && $data->hasProperty('token')) {
                    $data->addProperty('token', (new StringType)->setDescription('Secret token; store securely.'));
                }
                if (str_contains($operation->path, '/notifications') && $data instanceof ObjectType) {
                    $notification = $data->properties['items'] ?? $data;
                    $notification = $notification instanceof ArrayType ? $notification->items : $notification;
                    if ($notification instanceof ObjectType) {
                        $notification->addProperty('title', new StringType)->addProperty('body', new StringType);
                        $notification->getProperty('id')->format('uuid');
                        $notification->getProperty('read_at')->format('date-time');
                        $notification->getProperty('created_at')->format('date-time');
                    }
                }
                if ($data instanceof ObjectType && $data->hasProperty('items') && $data->hasProperty('meta')) {
                    if (! collect($operation->parameters)->contains(fn ($p): bool => $p instanceof Parameter && $p->name === 'page')) {
                        $operation->addParameters([Parameter::make('page', 'query')->setSchema(Schema::fromType((new IntegerType)->setMin(1)->default(1)))->description('Page number; fixed page size is 25.')]);
                    }
                }
            }
            $responses[] = $response;
        }
        foreach (array_unique($statuses) as $status) {
            $responses[] = $this->errorResponse($status);
        }
        foreach ($responses as $response) {
            $response->addHeader('X-Request-ID', new Header(description: 'Correlation ID for support and tracing.', schema: Schema::fromType(new StringType)));
            $response->addHeader('Content-Language', new Header(description: 'Resolved language for human-readable messages and validation errors.', schema: Schema::fromType((new StringType)->enum(['en', 'ar']))));
        }
        $operation->responses = $responses;
    }

    private function errorResponse(int $status): Response
    {
        $codes = array_values(array_map(
            fn (ErrorCode $code): string => $code->value,
            array_filter(ErrorCode::cases(), fn (ErrorCode $code): bool => ApiResponse::defaultStatus($code) === $status),
        ));
        $schema = (new ObjectType)
            ->addProperty('success', (new BooleanType)->enum([false]))
            ->addProperty('code', (new StringType)->enum($codes ?: ['INTERNAL_ERROR']))
            ->addProperty('message', (new StringType)->setDescription('Localized explanation.'))
            ->addProperty('errors', (new ObjectType)->additionalProperties((new ArrayType)->setItems(new StringType))->setDescription('Field-level validation messages; an empty object for errors without field details.'))
            ->setRequired(['success', 'code', 'message', 'errors']);
        $response = Response::make($status)->setDescription(match ($status) {
            400 => 'Invalid provider payload or signature.',
            401 => 'Missing, expired or invalid credentials.',
            403 => 'Wrong actor, inactive account or insufficient permission.',
            404 => 'Resource does not exist, is hidden, or is not owned by the caller.',
            409 => 'Conflicting state, duplicate value, resource in use or idempotency conflict.',
            413 => 'Webhook payload exceeds 262144 bytes. The application reports INTERNAL_ERROR with this HTTP status.',
            422 => 'Validation, locale or business rule failure.',
            429 => 'Rate limit, OTP cooldown or maximum attempts reached.',
            503 => 'External provider or required service unavailable. A payment outcome may be uncertain; retry with the same idempotency key.',
            default => 'Unexpected server error. Contact support with X-Request-ID.',
        })->setContent('application/json', Schema::fromType($schema));
        $response->addHeader('Content-Language', new Header(description: 'Resolved language for human-readable messages and validation errors.', schema: Schema::fromType((new StringType)->enum(['en', 'ar']))));
        if ($status === 429) {
            $response->addHeader('Retry-After', new Header(description: 'Seconds until retry; present for HTTP throttling, not all OTP domain errors.', schema: Schema::fromType(new IntegerType)));
        }

        return $response;
    }

    private function documentWebhook(Operation $operation): void
    {
        $operation->security = [];
        $operation->description($operation->description."\n\nFor provider=stripe, Stripe-Signature is required. For provider=myfatoorah, MyFatoorah-Signature is required and the body must use webhook v2 Event/Data fields. Configure the secure key in the merchant portal. MyFatoorah signatures cover event-specific fields; settlement additionally fetches authoritative payment/refund details.");
        $operation->addParameters([
            Parameter::make('Stripe-Signature', 'header')->required(false)->setSchema(Schema::fromType(new StringType))->description('Required for Stripe: timestamp and v1 HMAC over the exact raw body.'),
            Parameter::make('MyFatoorah-Signature', 'header')->required(false)->setSchema(Schema::fromType(new StringType))->description('Required for MyFatoorah: base64 HMAC-SHA256 over the ordered event-specific fields defined by webhook v2.'),
        ]);
        $event = (new ObjectType)
            ->addProperty('id', (new StringType)->examples(['evt_example']))
            ->addProperty('type', (new StringType)->examples(['payment_intent.succeeded']))
            ->addProperty('livemode', new BooleanType)
            ->addProperty('data', (new ObjectType)->addProperty('object', (new ObjectType)->setDescription('Stripe PaymentIntent or Refund object, including its provider ID and amount/currency fields.'))->setRequired(['object']))
            ->setRequired(['id', 'type', 'livemode', 'data']);
        $myfatoorah = (new ObjectType)
            ->addProperty('Event', (new ObjectType)->addProperty('Code', (new IntegerType)->enum([1, 2, 3, 4, 5, 6, 7]))->addProperty('Name', new StringType)->addProperty('Reference', new StringType)->setRequired(['Code']))
            ->addProperty('Data', (new ObjectType)->setDescription('Provider webhook v2 data for payment, refund, settlement, supplier, recurring, dispute, or supplier-update events. Preserve the provider payload.'))
            ->setRequired(['Event', 'Data']);
        $operation->addRequestBodyObject(RequestBodyObject::make()->required()->setContent('application/json', Schema::fromType((new AnyOf)->setItems([$event, $myfatoorah]))));
        foreach ([400, 413] as $status) {
            $operation->addResponse($this->errorResponse($status)->addHeader('X-Request-ID', new Header(description: 'Correlation ID for support and tracing.', schema: Schema::fromType(new StringType))));
        }
    }
}
