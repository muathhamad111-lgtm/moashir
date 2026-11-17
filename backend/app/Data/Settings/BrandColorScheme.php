<?php

declare(strict_types=1);

namespace App\Data\Settings;

final readonly class BrandColorScheme
{
    public function __construct(
        public string $primary,
        public string $onPrimary,
        public string $primaryText,
        public string $secondary,
        public string $onSecondary,
    ) {}

    /**
     * @return array{primary: string, on_primary: string, primary_text: string, secondary: string, on_secondary: string}
     */
    public function toArray(): array
    {
        return [
            'primary' => $this->primary,
            'on_primary' => $this->onPrimary,
            'primary_text' => $this->primaryText,
            'secondary' => $this->secondary,
            'on_secondary' => $this->onSecondary,
        ];
    }
}
