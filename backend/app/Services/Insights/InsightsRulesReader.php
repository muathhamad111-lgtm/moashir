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
use Psr\Log\LoggerInterface;

final readonly class InsightsRulesReader
{
    public function __construct(private LoggerInterface $logger) {}

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
        if (! $row instanceof Setting) {
            return GapThresholds::defaults();
        }

        try {
            return GapThresholds::fromArray($this->storedArrayOf($row));
        } catch (InvalidArgumentException) {
            $this->warnFallback($row);

            return GapThresholds::defaults();
        }
    }

    private function bandsFrom(?Setting $row): MaturityBounds
    {
        if (! $row instanceof Setting) {
            return MaturityBounds::defaults();
        }

        try {
            return MaturityBounds::fromArray($this->storedArrayOf($row));
        } catch (InvalidArgumentException) {
            $this->warnFallback($row);

            return MaturityBounds::defaults();
        }
    }

    /**
     * @return array<mixed>
     */
    private function storedArrayOf(Setting $row): array
    {
        if (! is_array($row->value)) {
            throw new InvalidArgumentException('The stored insights rule is not a map.');
        }

        return $row->value;
    }

    private function warnFallback(Setting $row): void
    {
        $this->logger->warning('insights.rules_fell_back_to_defaults', ['setting_id' => $row->id, 'setting_key' => $row->key]);
    }

    /**
     * @param  Collection<array-key, Setting>  $rows
     */
    private function effectiveSinceOf(Collection $rows): ?Carbon
    {
        return $rows->max(static fn (Setting $row): Carbon => $row->updated_at);
    }
}
