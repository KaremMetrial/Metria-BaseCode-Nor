<?php

namespace App\Support;

use App\Services\Payments\MyFatoorahCatalog;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

final class MyFatoorahDocumentationExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $name = $routeInfo->route->defaults['operation'] ?? null;
        if (! is_string($name)) {
            return;
        }
        $definition = app(MyFatoorahCatalog::class)->get($name);
        $examples = json_decode(file_get_contents(resource_path('myfatoorah-examples.json')), true, flags: JSON_THROW_ON_ERROR);
        $example = $examples[$name] ?? [];
        $operation->summary = Str::headline(str_replace('.', ' ', $name)).' — MyFatoorah';
        $operation->setOperationId('myfatoorah.'.str_replace('.', '_', $name));
        $operation->setTags(['Admin · MyFatoorah · '.Str::headline($definition['feature'])]);
        $operation->description($operation->description."\n\nFeature: `{$definition['feature']}`. [Official API contract]({$definition['source']}). Use provider field names with their original capitalization. Amounts here are decimal major units; wallet endpoints use integer minor units. Raw card numbers and CVVs are rejected. `customer_id` optionally associates a new business record with a local customer. `consent_reference` is required for subscriptions and saved-token charges."
            .($definition['mutates'] ? "\n\nOn 503, check the operation history. Replaying an uncertain operation returns 409 RECONCILIATION_REQUIRED without another provider mutation." : ''));
        $operation->parameters = array_values(array_filter($operation->parameters, fn ($parameter) => $parameter instanceof Parameter && in_array($parameter->name, ['X-Request-ID', 'X-Locale', 'locale'], true)));
        $schema = $definition['schema'];
        $schema['properties']['customer_id'] = ['type' => 'integer', 'minimum' => 1];
        $schema['properties']['consent_reference'] = ['type' => 'string', 'maxLength' => 255];
        if ($name === 'subscriptions.create') {
            $schema['required'][] = 'consent_reference';
        }
        if ($operation->method === 'get') {
            $operation->requestBodyObject = null;
            foreach ($schema['properties'] as $field => $property) {
                $type = $this->type($property);
                if (array_key_exists($field, $example)) {
                    $type->examples([$example[$field]]);
                }
                $operation->addParameters([Parameter::make($field, 'query')->required(in_array($field, $schema['required'], true))->setSchema(Schema::fromType($type))]);
            }
        } else {
            $operation->addRequestBodyObject(RequestBodyObject::make()->required($schema['required'] !== [])->setContent('application/json', Schema::fromType($this->type($schema)->examples([$example]))));
        }
        if ($definition['mutates']) {
            $operation->addParameters([Parameter::make('Idempotency-Key', 'header')->required(true)->setSchema(Schema::fromType((new StringType)->setMin(1)->setMax(128)->pattern('^[A-Za-z0-9._:-]+$')))->description('Globally unique business operation key. Reuse only for the identical request by the same administrator.')]);
        }
        $data = (new ObjectType)->addProperty('operation_id', (new StringType)->format('uuid')->nullable(true))
            ->addProperty('status', (new StringType)->enum(['succeeded']))
            ->addProperty('result', (new ObjectType)->additionalProperties(new MixedType)->setDescription('Provider Data, retaining provider field names. Array Data is wrapped as {items: [...]}; scalar or null Data as {value: ..., message: ...}. Treat saved tokens, encryption keys, and bank details as secrets. See the linked operation contract for fields.'))
            ->setRequired(['operation_id', 'status', 'result']);
        $envelope = (new ObjectType)->addProperty('success', (new BooleanType)->enum([true]))->addProperty('message', (new StringType)->nullable(true))->addProperty('data', $data)->setRequired(['success', 'message', 'data']);
        $operation->responses = array_values(array_filter($operation->responses, fn ($response) => (int) $response->code >= 400));
        $operation->addResponse(Response::make(200)->setDescription('Completed or replayed successfully.')
            ->addHeader('X-Request-ID', new Header(description: 'Correlation ID for support and tracing.', schema: Schema::fromType(new StringType)))
            ->addHeader('Content-Language', new Header(description: 'Resolved language for human-readable messages and validation errors.', schema: Schema::fromType((new StringType)->enum(['en', 'ar']))))
            ->setContent('application/json', Schema::fromType($envelope)));
    }

    private function type(array $schema): Type
    {
        $type = match ($schema['type'] ?? 'string') {
            'object' => (new ObjectType)->setRequired($schema['required'] ?? []),
            'array' => (new ArrayType)->setItems($this->type($schema['items'] ?? ['type' => 'string'])),
            'integer' => new IntegerType, 'number' => new NumberType, 'boolean' => new BooleanType,
            default => new StringType,
        };
        if ($type instanceof ObjectType) {
            foreach ($schema['properties'] ?? [] as $field => $property) {
                $type->addProperty($field, $this->type($property));
            }
        }
        if (isset($schema['enum'])) {
            $type->enum($schema['enum']);
        }
        if (isset($schema['format'])) {
            $type->format($schema['format']);
        }
        if ($type instanceof StringType && isset($schema['maxLength'])) {
            $type->setMax($schema['maxLength']);
        }
        if ($type instanceof NumberType) {
            if (isset($schema['minimum'])) {
                $type->setMin($schema['minimum']);
            }
            if (isset($schema['maximum'])) {
                $type->setMax($schema['maximum']);
            }
        }

        return $type;
    }
}
