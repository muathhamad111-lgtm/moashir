<?php

declare(strict_types=1);

namespace App\Enums\Dashboards;

/**
 * How a reporting period's comparison window is chosen.
 */
enum ComparisonBasis: string
{
    /** A finished month, quarter or year: the previous one, in full. */
    case PreviousFull = 'previous_full';

    /** A running month, quarter or year: the same span of the previous one. */
    case ElapsedSpan = 'elapsed_span';

    /** A custom range: the same number of days just before it. */
    case PrecedingDays = 'preceding_days';
}
