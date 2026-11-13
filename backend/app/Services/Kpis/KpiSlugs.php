<?php

declare(strict_types=1);

namespace App\Services\Kpis;

use App\Models\Kpis\Kpi;
use App\Support\ValueObjects\LocalizedContent;
use Illuminate\Support\Str;

final class KpiSlugs
{
    private const int MAX_LENGTH = 70;

    private const string FALLBACK = 'kpi';

    public function isTaken(string $slug, ?int $exceptId = null): bool
    {
        return Kpi::query()
            ->where('slug', $slug)
            ->when($exceptId !== null, static fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();
    }

    public function generateFor(LocalizedContent $name): string
    {
        $base = Str::limit(Str::slug($name->en !== '' ? $name->en : $name->ar), self::MAX_LENGTH, '');
        $base = trim($base, '-') === '' ? self::FALLBACK : trim($base, '-');
        $slug = $base;
        $suffix = 2;

        while ($this->isTaken($slug)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
