<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Data\Insights\InsightsRules;
use App\Enums\Settings\SettingKey;
use App\Models\Settings\Setting;
use App\Support\ValueObjects\GapThresholds;
use App\Support\ValueObjects\MaturityBounds;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class InsightsRulesReader
{
    public function read(): InsightsRules
    {
        $rows = Setting::query()
            ->whereIn('key', [SettingKey::InsightsGapThresholds->value, SettingKey::InsightsMaturityBands->value])
            ->get()
            ->keyBy('key');

        return new InsightsRules(
            $this->gapsFrom($rows->get(SettingKey::InsightsGapThresholds->value)),
            $this->bandsFrom($rows->get(SettingKey::InsightsMaturityBands->value)),
            $this->effectiveSinceOf($rows),
        );
    }

    private function gapsFrom(?Setting $row): GapThresholds
    {
        try {
            return is_array($row?->value) ? GapThresholds::fromArray($row->value) : GapThresholds::defaults();
        } catch (InvalidArgumentException) {
            return GapThresholds::defaults();
        }
    }

    private function bandsFrom(?Setting $row): MaturityBounds
    {
        try {
            return is_array($row?->value) ? MaturityBounds::fromArray($row->value) : MaturityBounds::defaults();
        } catch (InvalidArgumentException) {
            return MaturityBounds::defaults();
        }
    }

    /**
     * @param  Collection<array-key, Setting>  $rows
     */
    private function effectiveSinceOf(Collection $rows): ?Carbon
    {
        return $rows->max(static fn (Setting $row): Carbon => $row->updated_at);
    }
}
