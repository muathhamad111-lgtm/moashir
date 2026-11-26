<?php

declare(strict_types=1);

namespace App\Support\Finance;

use App\Data\Assessments\SubmissionProgress;
use App\Data\Finance\LinkedRequest;
use App\Support\Assessments\PathPresenter;

final class LinkedRequestPresenter
{
    /**
     * @return array<string, mixed>|null
     */
    public static function of(?LinkedRequest $linked): ?array
    {
        if (! $linked instanceof LinkedRequest) {
            return null;
        }

        $summary = [
            'number' => $linked->request->number,
            ...PathPresenter::of($linked->request->path),
            'status' => $linked->status,
            'progress' => self::progressOf($linked->progress),
        ];

        return $linked->isOpenable ? ['id' => $linked->request->id, ...$summary] : $summary;
    }

    /**
     * @return array{subs_complete: int, subs_total: int}|null
     */
    private static function progressOf(?SubmissionProgress $progress): ?array
    {
        return $progress instanceof SubmissionProgress ? ['subs_complete' => $progress->subsComplete, 'subs_total' => $progress->subsTotal] : null;
    }
}
