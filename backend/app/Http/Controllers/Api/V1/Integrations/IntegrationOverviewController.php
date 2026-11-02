<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Actions\Integrations\ListIntegrationOverview;
use App\Data\Integrations\IntegrationSnapshot;
use App\Enums\Integrations\IntegrationKind;
use App\Http\Resources\Integrations\IntegrationOverviewResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IntegrationOverviewController
{
    public function __invoke(Request $request, #[CurrentUser] User $actor, ListIntegrationOverview $overview): JsonResponse
    {
        $snapshots = $overview->handle($actor);
        $payment = array_values(array_filter($snapshots, static fn (IntegrationSnapshot $snapshot): bool => $snapshot->kind === IntegrationKind::Payment))[0] ?? null;

        return ApiResponse::data(IntegrationOverviewResource::collection($snapshots)->resolve($request), meta: [
            'payment_test_mode_in_production' => $payment instanceof IntegrationSnapshot && $payment->isProduction && $payment->mode()->isTest(),
        ]);
    }
}
