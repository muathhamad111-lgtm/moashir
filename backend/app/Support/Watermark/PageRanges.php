<?php

declare(strict_types=1);

namespace App\Support\Watermark;

final class PageRanges
{
    /**
     * @param  list<int>  $pages  ascending page numbers
     */
    public static function of(array $pages): string
    {
        $ranges = [];
        $start = null;
        $previous = null;

        foreach ($pages as $page) {
            if ($start !== null && $page === $previous + 1) {
                $previous = $page;

                continue;
            }

            if ($start !== null) {
                $ranges[] = self::range($start, (int) $previous);
            }

            $start = $page;
            $previous = $page;
        }

        if ($start !== null) {
            $ranges[] = self::range($start, (int) $previous);
        }

        return implode(',', $ranges);
    }

    private static function range(int $start, int $end): string
    {
        return $start === $end ? (string) $start : $start.'-'.$end;
    }
}
