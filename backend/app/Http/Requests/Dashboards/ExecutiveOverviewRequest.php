<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboards;

use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Dashboards\OverviewPeriod;
use App\Services\Dashboards\Overview\ReportingPeriods;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ExecutiveOverviewRequest extends FormRequest
{
    private const string DAY_FORMAT = 'Y-m-d';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $today = app(ReportingPeriods::class)->today()->format(self::DAY_FORMAT);

        return [
            'period' => ['nullable', 'string', Rule::enum(OverviewPeriod::class)],
            'from' => ['required_if:period,'.OverviewPeriod::Custom->value, 'nullable', 'date_format:'.self::DAY_FORMAT, 'before_or_equal:'.$today],
            'to' => ['required_if:period,'.OverviewPeriod::Custom->value, 'nullable', 'date_format:'.self::DAY_FORMAT, 'after_or_equal:from', 'before_or_equal:'.$today],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && $this->isCustom() && $this->period()->days() > $this->maxDays()) {
                $validator->errors()->add('to', __('validation.custom.overview_period.too_long', ['days' => $this->maxDays()]));
            }
        }];
    }

    public function period(): ReportingPeriod
    {
        $periods = app(ReportingPeriods::class);

        return $this->isCustom()
            ? $periods->custom($this->string('from')->toString(), $this->string('to')->toString())
            : $periods->preset($this->enum('period', OverviewPeriod::class) ?? OverviewPeriod::Month);
    }

    private function isCustom(): bool
    {
        return $this->enum('period', OverviewPeriod::class) === OverviewPeriod::Custom;
    }

    private function maxDays(): int
    {
        return config()->integer('moashir.dashboard.overview_max_days');
    }
}
