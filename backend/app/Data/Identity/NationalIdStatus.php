<?php

declare(strict_types=1);

namespace App\Data\Identity;

final readonly class NationalIdStatus
{
    public function __construct(public bool $required, public ?string $masked) {}

    /**
     * @return array{national_id_required: bool, has_national_id: bool, national_id_masked: string|null}
     */
    public function toArray(): array
    {
        return [
            'national_id_required' => $this->required,
            'has_national_id' => $this->masked !== null,
            'national_id_masked' => $this->masked,
        ];
    }
}
