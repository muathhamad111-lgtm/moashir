<?php

declare(strict_types=1);

namespace App\Http\Requests\Kpis;

use App\Data\Kpis\KpiMeasurement;
use App\Rules\KpiNumberInput;
use App\Support\ValueObjects\KpiNumber;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;

final class RecordKpiMeasurementRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $today = Date::now(config()->string('app.display_timezone'))->toDateString();

        return [
            'value' => ['required', new KpiNumberInput],
            'measured_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:'.$today],
        ];
    }

    public function measurement(): KpiMeasurement
    {
        return new KpiMeasurement(
            KpiNumber::tryFrom($this->input('value')) ?? new KpiNumber('0'),
            CarbonImmutable::createFromFormat('Y-m-d', $this->string('measured_on')->toString())?->startOfDay() ?? CarbonImmutable::today(),
        );
    }
}
