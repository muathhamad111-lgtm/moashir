<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Enums\Identity\TermsKind;
use App\Models\Identity\TermsVersion;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListLegalVersions
{
    private const array SUMMARY_COLUMNS = ['id', 'kind', 'version', 'title', 'effective_from', 'is_current', 'published_at', 'created_at', 'updated_at'];

    /**
     * @return LengthAwarePaginator<int, TermsVersion>
     */
    public function handle(TermsKind $kind, int $perPage): LengthAwarePaginator
    {
        return TermsVersion::query()
            ->ofKind($kind)
            ->select(self::SUMMARY_COLUMNS)
            ->orderByRaw('published_at DESC NULLS FIRST')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
