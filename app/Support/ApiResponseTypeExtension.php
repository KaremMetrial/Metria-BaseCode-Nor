<?php

namespace App\Support;

use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\StaticMethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Contracts\LiteralString;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\JsonResponse;

final class ApiResponseTypeExtension implements StaticMethodReturnTypeExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === ApiResponse::class;
    }

    public function getStaticMethodReturnType(StaticMethodCallEvent $event): ?Type
    {
        if ($event->name !== 'paginated') {
            return null;
        }

        $resource = $event->getArg('resource', 0);

        if (! $resource instanceof LiteralString) {
            return null;
        }

        return new Generic(JsonResponse::class, [
            new KeyedArrayType([
                new ArrayItemType_('success', new LiteralBooleanType(true)),
                new ArrayItemType_('message', new NullType),
                new ArrayItemType_('data', new KeyedArrayType([
                    new ArrayItemType_('items', new ArrayType(new ObjectType($resource->getValue()))),
                    new ArrayItemType_('meta', new KeyedArrayType(array_map(
                        fn (string $key): ArrayItemType_ => new ArrayItemType_($key, new IntegerType),
                        ['current_page', 'last_page', 'per_page', 'total'],
                    ))),
                ])),
            ]),
            new LiteralIntegerType(200),
            new ArrayType,
        ]);
    }
}
