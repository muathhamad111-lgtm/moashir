<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

use App\Data\Integrations\ResolvedCredentials;
use App\Data\Integrations\SmsBalance;

interface SmsAccountInspector extends ServesProvider
{
    public function balance(ResolvedCredentials $credentials): SmsBalance;
}
