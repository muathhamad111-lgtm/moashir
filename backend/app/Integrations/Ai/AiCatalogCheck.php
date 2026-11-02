<?php

declare(strict_types=1);

namespace App\Integrations\Ai;

use App\Contracts\Integrations\AiModelCatalog;
use App\Data\Integrations\AiModelInfo;
use App\Data\Integrations\ConnectionCheck;
use App\Data\Integrations\ConnectionTestResult;
use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Services\Integrations\ProviderHttp;

final class AiCatalogCheck
{
    public static function run(AiModelCatalog $catalog, ResolvedCredentials $credentials): ConnectionTestResult
    {
        $startedAt = (int) hrtime(true);
        $model = $credentials->string('model');

        try {
            $models = $catalog->models($credentials);
        } catch (ProviderCallFailed $failure) {
            return new ConnectionTestResult($catalog->provider(), $credentials->mode, ProviderHttp::elapsedMilliseconds($startedAt), [
                ConnectionCheck::failed('api_key_valid', $failure->kind->value),
            ], [], $failure->kind);
        }

        $isListed = array_any($models, static fn (AiModelInfo $info): bool => $info->id === $model);
        $checks = [
            ConnectionCheck::passed('api_key_valid'),
            $isListed ? ConnectionCheck::passed('model_available') : ConnectionCheck::failed('model_available', 'model_not_listed'),
        ];

        return new ConnectionTestResult(
            $catalog->provider(),
            $credentials->mode,
            ProviderHttp::elapsedMilliseconds($startedAt),
            $checks,
            ['model' => $model, 'models_count' => count($models)],
            $isListed ? null : ProviderErrorKind::Misconfigured,
        );
    }
}
