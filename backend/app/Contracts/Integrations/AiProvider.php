<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

use App\Data\Integrations\AiAnswer;
use App\Data\Integrations\AiPrompt;
use App\Data\Integrations\ResolvedCredentials;

interface AiProvider extends ServesProvider
{
    public function answer(ResolvedCredentials $credentials, AiPrompt $prompt): AiAnswer;
}
