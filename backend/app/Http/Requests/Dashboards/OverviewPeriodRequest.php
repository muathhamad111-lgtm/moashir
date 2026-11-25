<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboards;

use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Dashboards\OverviewPeriod;
use App\Services\Dashboards\Overview\ReportingPeriods;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The reporting period of a dashboard overview: a month, quarter or year (the current one unless
 * the year and month or quarter are given), or a custom range of past days. Nothing in the future
 * or wholly before the boards' first month can be chosen.
 */
final class OverviewPeriodRequest extends FormRequest
{
    private const string DAY_FORMAT = 'Y-m-d';

    private const int MONTHS_PER_QUARTER = 3;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $periods = app(ReportingPeriods::class);
        $today = $periods->today();
        $custom = OverviewPeriod::Custom->value;

        return [
            'period' => ['nullable', 'string', Rule::enum(OverviewPeriod::class)],
            'year' => ['prohibited_if:period,'.$custom, 'nullable', 'integer', 'min:'.$periods->firstDay()->year, 'max:'.$today->year],
            'month' => [Rule::requiredIf(fn (): bool => $this->isChosenBy(OverviewPeriod::Month)), 'prohibited_unless:period,'.OverviewPeriod::Month->value, 'nullable', 'integer', 'between:1,12'],
            'quarter' => [Rule::requiredIf(fn (): bool => $this->isChosenBy(OverviewPeriod::Quarter)), 'prohibited_unless:period,'.OverviewPeriod::Quarter->value, 'nullable', 'integer', 'between:1,4'],
            'from' => ['required_if:period,'.$custom, 'nullable', 'date_format:'.self::DAY_FORMAT, 'after_or_equal:'.$periods->firstDay()->format(self::DAY_FORMAT), 'before_or_equal:'.$today->format(self::DAY_FORMAT)],
            'to' => ['required_if:period,'.$custom, 'nullable', 'date_format:'.self::DAY_FORMAT, 'after_or_equal:from', 'before_or_equal:'.$today->format(self::DAY_FORMAT)],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->isCustom() && $this->period()->days() > $this->maxDays()) {
                $validator->errors()->add('to', __('validation.custom.overview_period.too_long', ['days' => $this->maxDays()]));
            }

            $outOfRange = $this->isCustom() ? null : $this->outOfRangeReason();
            if ($outOfRange !== null) {
                $validator->errors()->add('year', __('validation.custom.overview_period.'.$outOfRange));
            }
        }];
    }

    /**
     * Why the chosen month, quarter or year cannot be shown: it has not started, or it ended
     * before the boards' first month; null when it can.
     */
    private function outOfRangeReason(): ?string
    {
        $start = $this->chosenStart();
        $periods = app(ReportingPeriods::class);

        return match (true) {
            ! $start instanceof CarbonImmutable => null,
            $start->greaterThan($periods->today()) => 'future',
            $start->addMonthsNoOverflow($this->preset()->months() ?? 0)->lessThanOrEqualTo($periods->firstDay()) => 'before_first',
            default => null,
        };
    }

    public function period(): ReportingPeriod
    {
        $periods = app(ReportingPeriods::class);

        return $this->isCustom()
            ? $periods->custom($this->string('from')->toString(), $this->string('to')->toString())
            : $periods->preset($this->preset(), $this->chosenStart());
    }

    private function preset(): OverviewPeriod
    {
        return $this->enum('period', OverviewPeriod::class) ?? OverviewPeriod::Month;
    }

    /**
     * The first day of the chosen month, quarter or year; null for the current one.
     */
    private function chosenStart(): ?CarbonImmutable
    {
        if (! $this->filled('year')) {
            return null;
        }

        $month = match ($this->preset()) {
            OverviewPeriod::Month => $this->integer('month'),
            OverviewPeriod::Quarter => ($this->integer('quarter') - 1) * self::MONTHS_PER_QUARTER + 1,
            default => 1,
        };

        return app(ReportingPeriods::class)->monthStart($this->integer('year'), $month);
    }

    private function isChosenBy(OverviewPeriod $preset): bool
    {
        return $this->preset() === $preset && $this->filled('year');
    }

    private function isCustom(): bool
    {
        return $this->preset() === OverviewPeriod::Custom;
    }

    private function maxDays(): int
    {
        return config()->integer('moashir.dashboard.overview_max_days');
    }
}
