<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

use App\Data\Integrations\AiModelInfo;
use App\Data\Integrations\ResolvedCredentials;

interface AiModelCatalog extends ServesProvider
{
    /**
     * @return list<AiModelInfo>
     */
    public function models(ResolvedCredentials $credentials): array;
}
