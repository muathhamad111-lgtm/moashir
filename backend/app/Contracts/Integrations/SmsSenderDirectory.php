<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

use App\Data\Integrations\ResolvedCredentials;
use App\Data\Integrations\SmsSender;

interface SmsSenderDirectory extends ServesProvider
{
    /**
     * @return list<SmsSender>
     */
    public function senders(ResolvedCredentials $credentials): array;
}
