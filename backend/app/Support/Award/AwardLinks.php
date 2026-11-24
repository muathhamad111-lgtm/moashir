<?php

declare(strict_types=1);

namespace App\Support\Award;

final class AwardLinks
{
    public static function cycle(int $cycleId): string
    {
        return '/admin/award/cycles/'.$cycleId.'/setup';
    }

    public static function committee(int $cycleId): string
    {
        return '/admin/award/cycles/'.$cycleId.'/committee';
    }

    public static function secretaryRequest(int $cycleId, int $assessmentId): string
    {
        return '/admin/award/cycles/'.$cycleId.'/secretary/'.$assessmentId;
    }

    public static function results(int $cycleId): string
    {
        return '/admin/award/cycles/'.$cycleId.'/results';
    }

    public static function national(int $cycleId): string
    {
        return self::results($cycleId).'/national';
    }

    public static function entityResult(int $assessmentId): string
    {
        return '/assessment/advanced/result/'.$assessmentId;
    }

    public static function filtering(int $cycleId, int $assessmentId): string
    {
        return '/admin/award/cycles/'.$cycleId.'/filtering/'.$assessmentId;
    }
}
