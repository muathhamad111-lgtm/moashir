<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Data\Periods\PeriodicSettingsUpdate;
use App\Services\Periods\PeriodicSettingsReader;
use App\Support\Periods\PeriodicSettingsRules;
use Illuminate\Foundation\Http\FormRequest;

final class PreviewPeriodicSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return PeriodicSettingsRules::for(PeriodicSettingsRules::boundingCadence($this, app(PeriodicSettingsReader::class)->read()->cadence));
    }

    public function toUpdate(): PeriodicSettingsUpdate
    {
        return PeriodicSettingsRules::updateFrom($this, null);
    }
}
