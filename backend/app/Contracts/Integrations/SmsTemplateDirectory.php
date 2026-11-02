<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

use App\Data\Integrations\ProviderSmsTemplate;
use App\Data\Integrations\ResolvedCredentials;

interface SmsTemplateDirectory extends ServesProvider
{
    /**
     * @return list<ProviderSmsTemplate>
     */
    public function templates(ResolvedCredentials $credentials): array;
}
