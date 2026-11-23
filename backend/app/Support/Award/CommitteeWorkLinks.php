<?php

declare(strict_types=1);

namespace App\Support\Award;

/**
 * Links into a committee assessor's own work (assignments, one evaluation, discussions), kept apart from the
 * cycle and secretary links in AwardLinks.
 */
final class CommitteeWorkLinks
{
    public static function assignments(): string
    {
        return '/admin/committee/assignments';
    }

    public static function evaluation(int $assignmentId): string
    {
        return '/admin/committee/assignments/'.$assignmentId;
    }

    public static function discussions(): string
    {
        return '/admin/committee/discussions';
    }
}
