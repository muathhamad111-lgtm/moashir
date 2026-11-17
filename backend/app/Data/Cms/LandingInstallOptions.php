<?php

declare(strict_types=1);

namespace App\Data\Cms;

final readonly class LandingInstallOptions
{
    public function __construct(public ?string $editorEmail, public bool $replacesCurrentLanding) {}
}
