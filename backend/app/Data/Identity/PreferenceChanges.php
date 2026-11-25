<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Enums\Identity\Locale;
use App\Enums\Identity\ThemePreference;

final readonly class PreferenceChanges
{
    /**
     * @param  array<string, list<string>|null>  $tableColumns  the visible columns per table; null restores a table's defaults
     */
    public function __construct(
        public ?Locale $locale = null,
        public ?ThemePreference $theme = null,
        public array $tableColumns = [],
    ) {}
}
