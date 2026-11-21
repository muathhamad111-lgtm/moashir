<?php

declare(strict_types=1);

namespace App\Data\Insights;

use App\Enums\Insights\MaturityBand;
use App\Support\ValueObjects\LocalizedText;

final readonly class MaturityTextBook
{
    /**
     * @param  array<string, LocalizedText>  $generic
     * @param  array<string, array<string, LocalizedText>>  $overrides
     */
    public function __construct(private array $generic, private array $overrides) {}

    public function resolve(MaturityBand $band, string $mainCode): ?LocalizedText
    {
        return $this->overrides[$band->value][$mainCode] ?? $this->generic[$band->value] ?? null;
    }
}
