<?php

declare(strict_types=1);

use App\Enums\Settings\SettingKey;
use App\Models\Settings\Setting;
use App\Services\Insights\InsightsRulesReader;
use App\Support\ValueObjects\GapThresholds;
use App\Support\ValueObjects\MaturityBounds;
use Illuminate\Support\Facades\Log;

function storeInsightsRule(SettingKey $key, mixed $value): Setting
{
    $row = Setting::query()->updateOrCreate(['key' => $key->value], ['group' => $key->group(), 'type' => $key->type()]);
    $row->forceFill(['value' => $value])->save();

    return $row;
}

it('falls back to the default rules and warns with the setting key when a stored rule is corrupt', function (SettingKey $key, mixed $corrupt): void {
    Log::spy();
    $row = storeInsightsRule($key, $corrupt);

    $rules = app(InsightsRulesReader::class)->read();

    expect($rules->gaps->toArray())->toBe(GapThresholds::defaults()->toArray())
        ->and($rules->bands->toArray())->toBe(MaturityBounds::defaults()->toArray());
    Log::shouldHaveReceived('warning')->with('insights.rules_fell_back_to_defaults', ['setting_id' => $row->id, 'setting_key' => $key->value])->once();
})->with([
    'gap thresholds out of order' => [SettingKey::InsightsGapThresholds, ['critical_below' => '90.00', 'strength_from' => '10.00', 'version' => 2]],
    'gap thresholds not a map' => [SettingKey::InsightsGapThresholds, 'fifty'],
    'maturity bands missing a bound' => [SettingKey::InsightsMaturityBands, ['developing_from' => '50.00', 'version' => 2]],
    'maturity bands not a map' => [SettingKey::InsightsMaturityBands, 70],
]);

it('reads valid stored rules without warning', function (): void {
    Log::spy();
    storeInsightsRule(SettingKey::InsightsGapThresholds, ['critical_below' => '45.50', 'strength_from' => '85.00', 'version' => 2]);

    expect(app(InsightsRulesReader::class)->read()->gaps->toArray())->toBe(['critical_below' => '45.50', 'strength_from' => '85.00', 'version' => 2]);
    Log::shouldNotHaveReceived('warning');
});

it('uses the defaults without warning while no rule has been stored', function (): void {
    Log::spy();

    expect(app(InsightsRulesReader::class)->read()->bands->toArray())->toBe(MaturityBounds::defaults()->toArray());
    Log::shouldNotHaveReceived('warning');
});
