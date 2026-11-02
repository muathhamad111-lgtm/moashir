<?php

declare(strict_types=1);

namespace App\Data\Files;

use App\Enums\Files\MalwareScanStatus;

final readonly class ScanVerdict
{
    public function __construct(
        public MalwareScanStatus $status,
        public string $engine,
        public ?string $signature = null,
    ) {}

    public static function clean(string $engine): self
    {
        return new self(MalwareScanStatus::Clean, $engine);
    }

    public static function infected(string $engine, string $signature): self
    {
        return new self(MalwareScanStatus::Infected, $engine, mb_substr($signature, 0, 120));
    }

    public static function failed(string $engine): self
    {
        return new self(MalwareScanStatus::Failed, $engine);
    }
}
