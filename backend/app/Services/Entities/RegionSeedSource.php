<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Data\Entities\CitySeed;
use App\Data\Entities\RegionSeed;
use App\Support\ScalarValue;
use App\Support\ValueObjects\LocalizedText;
use InvalidArgumentException;

final class RegionSeedSource
{
    /**
     * @return list<RegionSeed>
     */
    public function load(string $path): array
    {
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new InvalidArgumentException('The regions seed file is missing.');
        }

        $data = $this->record(json_decode($contents, true, 16, JSON_THROW_ON_ERROR));
        $regions = [];

        foreach ($this->records($data, 'regions') as $position => $region) {
            $regions[] = $this->region($region, $position + 1);
        }

        return $regions;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function region(array $data, int $sortOrder): RegionSeed
    {
        $cities = [];

        foreach ($this->records($data, 'cities') as $position => $city) {
            $cities[] = new CitySeed($this->code($city), $this->name($city), $position + 1);
        }

        return new RegionSeed($this->code($data), $this->name($data), $sortOrder, $cities);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function code(array $data): string
    {
        $code = ScalarValue::string($data['code'] ?? null);

        if (preg_match('/^[a-z0-9_]{1,40}$/', $code) !== 1) {
            throw new InvalidArgumentException('The regions seed file holds an invalid code.');
        }

        return $code;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function name(array $data): LocalizedText
    {
        return LocalizedText::fromArray($this->record($data['name'] ?? null));
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<array<array-key, mixed>>
     */
    private function records(array $data, string $key): array
    {
        $items = $data[$key] ?? [];

        return is_array($items) ? array_values(array_map($this->record(...), $items)) : [];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function record(mixed $value): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('The regions seed file is malformed.');
        }

        return $value;
    }
}
