<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Data\Periods\PeriodicSettingsUpdate;
use App\Enums\Periods\PeriodCadence;
use App\Services\Periods\PeriodicSettingsReader;
use App\Support\Periods\PeriodicSettingsRules;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePeriodicSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...PeriodicSettingsRules::for($this->boundingCadence()),
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function toUpdate(): PeriodicSettingsUpdate
    {
        return PeriodicSettingsRules::updateFrom($this, $this->filled('reason') ? $this->string('reason')->trim()->toString() : null);
    }

    private function boundingCadence(): PeriodCadence
    {
        return PeriodicSettingsRules::boundingCadence($this, app(PeriodicSettingsReader::class)->read()->cadence);
    }
}
