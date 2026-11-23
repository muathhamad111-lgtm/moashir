<?php

declare(strict_types=1);

namespace App\Actions\Nda;

use App\Enums\Identity\TermsKind;
use App\Models\Identity\TermsVersion;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The agreement versions, draft first, each with its publisher and how many users accepted it.
 */
final class ListNdaVersions
{
    /**
     * @return LengthAwarePaginator<int, TermsVersion>
     */
    public function handle(int $perPage): LengthAwarePaginator
    {
        return TermsVersion::query()
            ->ofKind(TermsKind::Nda)
            ->withCount('acceptances')
            ->with('publishedBy:id,name')
            ->orderByRaw('published_at DESC NULLS FIRST')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
