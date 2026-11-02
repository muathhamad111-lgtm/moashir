<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Actions\Integrations\LoadMsegatTemplates;
use App\Data\Integrations\ProviderSmsTemplate;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class MsegatTemplateController
{
    public function __invoke(LoadMsegatTemplates $templates): JsonResponse
    {
        return ApiResponse::data(array_map(static fn (ProviderSmsTemplate $template): array => $template->toArray(), $templates->handle()));
    }
}
