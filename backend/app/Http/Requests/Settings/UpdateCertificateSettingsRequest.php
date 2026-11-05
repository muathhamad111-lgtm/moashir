<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Data\Certificates\CertificateSettingsUpdate;
use App\Enums\Certificates\CertificateLevel;
use App\Support\ValueObjects\LevelThresholds;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

final class UpdateCertificateSettingsRequest extends FormRequest
{
    private const string PERCENT_PATTERN = '/^\d{1,3}(\.\d{1,2})?$/';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'accredited_issuance_enabled' => ['sometimes', 'required', 'boolean'],
            'level_thresholds' => ['sometimes', 'required', 'array:'.implode(',', CertificateLevel::values())],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];

        foreach (CertificateLevel::values() as $level) {
            $rules['level_thresholds.'.$level] = ['required_with:level_thresholds', 'string', 'regex:'.self::PERCENT_PATTERN, 'numeric', 'gt:0', 'lte:100'];
        }

        return $rules;
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && $this->has('level_thresholds') && ! $this->thresholds() instanceof LevelThresholds) {
                $validator->errors()->add('level_thresholds', __('validation.custom.level_thresholds.ascending'));
            }
        }];
    }

    public function toUpdate(): CertificateSettingsUpdate
    {
        return new CertificateSettingsUpdate(
            $this->has('accredited_issuance_enabled') ? $this->boolean('accredited_issuance_enabled') : null,
            $this->has('level_thresholds') ? $this->thresholds() : null,
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
        );
    }

    private function thresholds(): ?LevelThresholds
    {
        try {
            return LevelThresholds::fromArray((array) $this->input('level_thresholds', []));
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
