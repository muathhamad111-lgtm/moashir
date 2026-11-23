<?php

declare(strict_types=1);

namespace App\Support\Award;

use App\Data\Award\OpenCycleOption;
use App\Models\Assessments\Assessment;
use App\Models\Entities\Region;
use App\Support\Assessments\StatusMask;
use App\Support\Entities\EntityPresenter;
use App\Support\Finance\PriceQuotePresenter;

final class OpenCycleOptionPresenter
{
    /**
     * @param  list<OpenCycleOption>  $options
     * @return list<array<string, mixed>>
     */
    public static function presentAll(array $options, mixed $viewer): array
    {
        return array_map(static fn (OpenCycleOption $option): array => self::present($option, $viewer), $options);
    }

    /**
     * @return array<string, mixed>
     */
    private static function present(OpenCycleOption $option, mixed $viewer): array
    {
        $cycle = $option->cycle;

        return [
            'id' => $cycle->id,
            'name' => $cycle->name->toArray(),
            'year' => $cycle->year,
            'submission_start' => $cycle->submission_start->toIso8601String(),
            'submission_end' => $cycle->submission_end->toIso8601String(),
            'min_entity_age_years' => $cycle->min_entity_age_years,
            'regions' => array_values($cycle->regions->map(static fn (Region $region): array => EntityPresenter::place($region))->all()),
            'entry_fee' => PriceQuotePresenter::present($option->entryFee),
            'eligibility' => ['eligible' => $option->isEligible(), 'reason' => $option->ineligibility?->value],
            'request' => self::requestOf($option->request, $viewer),
        ];
    }

    /**
     * @return array{id: int, number: string, status: string, is_open: bool}|null
     */
    private static function requestOf(?Assessment $request, mixed $viewer): ?array
    {
        return $request instanceof Assessment ? [
            'id' => $request->id,
            'number' => $request->request_number,
            'status' => StatusMask::statusFor($request, $viewer),
            'is_open' => StatusMask::isOpenFor($request, $viewer),
        ] : null;
    }
}
