<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Enums\Identity\Locale;
use App\Enums\Identity\ThemePreference;

final readonly class PreferenceChanges
{
    public function __construct(public ?Locale $locale = null, public ?ThemePreference $theme = null) {}
}
