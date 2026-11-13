<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Data\Settings\InsightsSettingsUpdate;
use App\Support\ScalarValue;
use App\Support\ValueObjects\GapThresholds;
use App\Support\ValueObjects\MaturityBounds;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

final class UpdateInsightsSettingsRequest extends FormRequest
{
    private const string PERCENT_PATTERN = '/^\d{1,3}(\.\d{1,2})?$/';

    private const array GAP_FIELDS = ['critical_below', 'strength_from'];

    private const array BAND_FIELDS = ['developing_from', 'mature_from', 'leading_from'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'gap_thresholds' => ['sometimes', 'required', 'array:'.implode(',', self::GAP_FIELDS)],
            ...$this->percentRules('gap_thresholds', self::GAP_FIELDS),
            'maturity_bands' => ['sometimes', 'required', 'array:'.implode(',', self::BAND_FIELDS)],
            ...$this->percentRules('maturity_bands', self::BAND_FIELDS),
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
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

            if ($this->has('gap_thresholds') && ! $this->gaps() instanceof GapThresholds) {
                $validator->errors()->add('gap_thresholds', __('validation.custom.gap_thresholds.ascending'));
            }

            if ($this->has('maturity_bands') && ! $this->bands() instanceof MaturityBounds) {
                $validator->errors()->add('maturity_bands', __('validation.custom.maturity_bands.ascending'));
            }
        }];
    }

    public function toUpdate(): InsightsSettingsUpdate
    {
        return new InsightsSettingsUpdate(
            $this->has('gap_thresholds') ? $this->gaps() : null,
            $this->has('maturity_bands') ? $this->bands() : null,
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
        );
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function percentRules(string $group, array $fields): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $rules[$group.'.'.$field] = ['required_with:'.$group, 'string', 'regex:'.self::PERCENT_PATTERN, 'numeric', 'gt:0', 'lte:100'];
        }

        return $rules;
    }

    private function gaps(): ?GapThresholds
    {
        try {
            return GapThresholds::of($this->percent('gap_thresholds.critical_below'), $this->percent('gap_thresholds.strength_from'));
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function bands(): ?MaturityBounds
    {
        try {
            return MaturityBounds::of(
                $this->percent('maturity_bands.developing_from'),
                $this->percent('maturity_bands.mature_from'),
                $this->percent('maturity_bands.leading_from'),
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function percent(string $key): string
    {
        return ScalarValue::string($this->input($key));
    }
}
