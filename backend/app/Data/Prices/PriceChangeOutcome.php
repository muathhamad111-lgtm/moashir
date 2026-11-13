<?php

declare(strict_types=1);

namespace App\Data\Prices;

final readonly class PriceChangeOutcome
{
    public function __construct(public bool $changed, public ?int $cancelledVersionId) {}

    /**
     * @return array{changed: bool, cancelled_version_id: int|null}
     */
    public function toMeta(): array
    {
        return ['changed' => $this->changed, 'cancelled_version_id' => $this->cancelledVersionId];
    }
}
