<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Data\Settings\PaletteColor;
use App\Enums\Settings\AccentToken;
use App\Support\Branding\ContrastRatio;
use App\Support\ScalarValue;
use Illuminate\Contracts\Config\Repository;

final readonly class BrandPalette
{
    public function __construct(private Repository $config) {}

    /**
     * @return list<PaletteColor>
     */
    public function colors(): array
    {
        $colors = [];

        foreach (AccentToken::cases() as $token) {
            $color = $this->colorOf($token);

            if ($color instanceof PaletteColor) {
                $colors[] = $color;
            }
        }

        return $colors;
    }

    public function colorOf(AccentToken $token): ?PaletteColor
    {
        $entry = $this->config->get('moashir.branding.palette.'.$token->value);

        if (! is_array($entry)) {
            return null;
        }

        $fill = mb_strtolower(ScalarValue::string($entry['fill'] ?? null));
        $textOnLight = mb_strtolower(ScalarValue::string($entry['text_on_light'] ?? null));

        return ContrastRatio::isHex($fill) && ContrastRatio::isHex($textOnLight) ? new PaletteColor($token, $fill, $textOnLight) : null;
    }
}
