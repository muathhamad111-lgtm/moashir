<?php

declare(strict_types=1);

namespace App\Data\Kpis;

use App\Data\Files\ImageChange;
use App\Support\ValueObjects\KpiNumber;

final readonly class KpiChanges
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public array $attributes, public ?ImageChange $image) {}

    public function isEmpty(): bool
    {
        return $this->attributes === [] && ! $this->image instanceof ImageChange;
    }

    public function changesValue(): bool
    {
        return array_key_exists('value', $this->attributes);
    }

    public function value(): ?KpiNumber
    {
        $value = $this->attributes['value'] ?? null;

        return $value instanceof KpiNumber ? $value : null;
    }

    public function featured(): ?bool
    {
        $featured = $this->attributes['show_on_homepage'] ?? null;

        return is_bool($featured) ? $featured : null;
    }

    public function slug(): ?string
    {
        $slug = $this->attributes['slug'] ?? null;

        return is_string($slug) ? $slug : null;
    }
}
