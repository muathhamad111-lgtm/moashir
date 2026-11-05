<?php

declare(strict_types=1);

namespace App\Data\Settings;

final readonly class ContrastCheck
{
    public function __construct(
        public string $pair,
        public string $foreground,
        public string $background,
        public string $ratio,
        public string $minimum,
        public bool $passes,
    ) {}

    /**
     * @return array{pair: string, foreground: string, background: string, ratio: string, minimum: string}
     */
    public function toArray(): array
    {
        return [
            'pair' => $this->pair,
            'foreground' => $this->foreground,
            'background' => $this->background,
            'ratio' => $this->ratio,
            'minimum' => $this->minimum,
        ];
    }
}
