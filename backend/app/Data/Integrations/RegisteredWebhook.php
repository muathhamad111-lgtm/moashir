<?php

declare(strict_types=1);

namespace App\Data\Integrations;

final readonly class RegisteredWebhook
{
    public function __construct(public string $id, public string $url) {}
}
