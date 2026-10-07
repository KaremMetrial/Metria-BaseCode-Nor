<?php

namespace App\Support;

use App\Enums\GovernorateType;
use App\Enums\PaymentStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Enums\WalletTransactionType;
use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;

final class ApiSchemaDocumentation implements DocumentTransformer
{
    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        foreach ($document->components->schemas as $name => $schema) {
            $resource = class_basename($name);
            if (! str_ends_with($resource, 'Resource') || ! $schema->type instanceof ObjectType) {
                continue;
            }
            $body = $schema->type;
            foreach ($body->properties as $field => $property) {
                if (str_ends_with($field, '_at')) {
                    $property?->format('date-time');
                }
                if ($field === 'uuid') {
                    $property?->format('uuid');
                }
            }
            $translations = $body->properties['translations'] ?? null;
            if ($translations instanceof ArrayType) {
                $body->addProperty('translations', (new ObjectType)->additionalProperties($translations->items)
                    ->setDescription('Translations keyed by locale (en, ar). Included only when loaded and authorized.'));
            }
            $enums = match ($resource) {
                'UserResource' => ['type' => UserType::cases(), 'status' => UserStatus::cases()],
                'PaymentResource' => ['status' => PaymentStatus::cases()],
                'WalletTransactionResource' => ['direction' => WalletTransactionType::cases()],
                'GovernorateResource', 'AdminGovernorateResource' => ['type' => GovernorateType::cases()],
                default => [],
            };
            foreach ($enums as $field => $cases) {
                $body->getProperty($field)->enum(array_column($cases, 'value'));
            }
            if ($resource === 'PaymentResource') {
                $body->getProperty('client_secret')->setDescription('Included only for the payment owner while pending or processing. Never log or share this value.');
            }
            foreach (['amount', 'balance', 'balance_after', 'refunded_amount'] as $field) {
                if ($body->hasProperty($field)) {
                    $body->getProperty($field)->setDescription('Integer amount in the currency’s smallest unit.');
                }
            }
        }
    }
}
