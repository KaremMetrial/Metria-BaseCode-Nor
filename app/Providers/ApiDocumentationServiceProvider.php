<?php

namespace App\Providers;

use App\Support\ApiSchemaDocumentation;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\ServiceProvider;

final class ApiDocumentationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Scramble::configure()->withDocumentTransformers([ApiSchemaDocumentation::class]);
    }
}
