<?php

declare(strict_types=1);

namespace App\Data\Integrations;

final readonly class SmsDriverSelection
{
    public function __construct(public bool $usesFakeGateway) {}
}
