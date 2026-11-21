<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Data\Settings\BrandColors;
use Illuminate\Contracts\Config\Repository;

final readonly class DefaultBrandColors
{
    public function __construct(private Repository $config) {}

    public function get(): BrandColors
    {
        return new BrandColors(
            $this->config->string('moashir.branding.default_colors.primary'),
            $this->config->string('moashir.branding.default_colors.secondary'),
        );
    }
}
