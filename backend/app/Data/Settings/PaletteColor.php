<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Enums\Settings\AccentToken;

final readonly class PaletteColor
{
    public function __construct(public AccentToken $token, public string $fill, public string $textOnLight) {}
}
