<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Enums\Entities\EntityProfileField;

final readonly class ProfileChangePlaces
{
    /**
     * @param  array<int, array{id: int, code: string, name: array{ar: string, en: string}}>  $regions
     * @param  array<int, array{id: int, code: string, name: array{ar: string, en: string}}>  $cities
     */
    public function __construct(
        public array $regions = [],
        public array $cities = [],
    ) {}

    public function present(EntityProfileField $field, mixed $value): mixed
    {
        $places = match ($field) {
            EntityProfileField::Region => $this->regions,
            EntityProfileField::City => $this->cities,
            default => null,
        };

        if ($places === null || ! is_int($value)) {
            return $value;
        }

        return $places[$value] ?? ['id' => $value, 'code' => null, 'name' => null];
    }
}
