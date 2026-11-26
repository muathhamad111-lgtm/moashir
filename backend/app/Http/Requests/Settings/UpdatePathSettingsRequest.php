<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Data\Assessments\UploadLimits;
use App\Data\Settings\PathSettingsUpdate;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Files\EvidenceFileType;
use App\Enums\Settings\PathEntityText;
use App\Enums\Settings\PathIdentityField;
use App\Models\Identity\User;
use App\Services\Settings\AssessmentSettingsReader;
use App\Support\Localization\LocalizedFieldRules;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePathSettingsRequest extends FormRequest
{
    private const string TEXTS = 'entity_texts';

    private const string NAME = 'name';

    private const array REVIEWED_FIELDS = ['review_sla_days', 'upload_max_file_mb', 'evidence_allowed_types', 'evidence_max_files_per_sub', self::TEXTS];

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->identityRules(),
            ...($this->settingsPath()->isReviewed() ? $this->reviewedRules() : $this->inapplicableRules()),
            self::NAME => ['prohibited'],
            PathSettingsUpdate::PRICE => ['prohibited'],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [
            self::NAME.'.prohibited' => __('validation.custom.path_settings.name_fixed'),
            PathSettingsUpdate::PRICE.'.prohibited' => __('validation.custom.price.moved'),
        ];

        foreach (self::REVIEWED_FIELDS as $field) {
            $messages[$field.'.prohibited'] = __('validation.custom.path_settings.not_applicable');
        }

        return $messages;
    }

    public function settingsPath(): AssessmentPath
    {
        $path = $this->route('path');

        return $path instanceof AssessmentPath ? $path : AssessmentPath::from(is_string($path) ? $path : '');
    }

    public function toUpdate(): PathSettingsUpdate
    {
        return new PathSettingsUpdate(
            $this->filled('review_sla_days') ? $this->integer('review_sla_days') : null,
            $this->filled('upload_max_file_mb') ? $this->integer('upload_max_file_mb') : null,
            $this->has('evidence_allowed_types') ? $this->types() : null,
            $this->filled('evidence_max_files_per_sub') ? $this->integer('evidence_max_files_per_sub') : null,
            $this->textChanges(),
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
            $this->localized(PathIdentityField::Description),
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private function identityRules(): array
    {
        $rules = [];

        foreach (PathIdentityField::cases() as $field) {
            $rules = [...$rules, ...LocalizedFieldRules::complete($field->value, $field->maxLength())];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewedRules(): array
    {
        $sla = app(AssessmentSettingsReader::class)->slaLimits();

        return [
            'review_sla_days' => ['sometimes', 'required', 'integer', 'between:'.$sla['min'].','.$sla['max']],
            'upload_max_file_mb' => ['sometimes', 'required', 'integer', 'between:1,'.UploadLimits::MAX_FILE_MB],
            'evidence_allowed_types' => ['sometimes', 'required', 'array', 'min:1', 'max:'.count(EvidenceFileType::cases())],
            'evidence_allowed_types.*' => ['required', 'string', 'distinct', Rule::enum(EvidenceFileType::class)],
            'evidence_max_files_per_sub' => ['sometimes', 'required', 'integer', 'between:1,'.UploadLimits::MAX_FILES_PER_SUB],
            self::TEXTS => ['sometimes', 'required', 'array:'.implode(',', PathEntityText::values())],
            ...$this->textRules(),
        ];
    }

    /**
     * Settings that only exist on the reviewed paths are refused on the self assessment instead of being ignored.
     *
     * @return array<string, list<string>>
     */
    private function inapplicableRules(): array
    {
        return array_fill_keys(self::REVIEWED_FIELDS, ['prohibited']);
    }

    private function localized(PathIdentityField $field): ?LocalizedText
    {
        if (! $this->filled($field->value)) {
            return null;
        }

        return new LocalizedText(
            $this->string($field->value.'.ar')->trim()->toString(),
            $this->string($field->value.'.en')->trim()->toString(),
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private function textRules(): array
    {
        $rules = [];

        foreach (PathEntityText::values() as $key) {
            $field = self::TEXTS.'.'.$key;
            $rules[$field] = ['nullable', 'array:ar,en'];
            $rules[$field.'.ar'] = ['required_with:'.$field, 'string', 'max:'.PathEntityText::MAX_LENGTH];
            $rules[$field.'.en'] = ['required_with:'.$field, 'string', 'max:'.PathEntityText::MAX_LENGTH];
        }

        return $rules;
    }

    /**
     * @return array<value-of<PathEntityText>, LocalizedText|null>
     */
    private function textChanges(): array
    {
        $submitted = $this->input(self::TEXTS);
        $changes = [];

        foreach (PathEntityText::cases() as $key) {
            if (! is_array($submitted) || ! array_key_exists($key->value, $submitted)) {
                continue;
            }

            $field = self::TEXTS.'.'.$key->value;
            $changes[$key->value] = $this->filled($field) ? new LocalizedText(
                $this->string($field.'.ar')->trim()->toString(),
                $this->string($field.'.en')->trim()->toString(),
            ) : null;
        }

        return $changes;
    }

    /**
     * @return list<EvidenceFileType>
     */
    private function types(): array
    {
        $types = [];

        foreach ((array) $this->input('evidence_allowed_types', []) as $value) {
            $type = is_string($value) ? EvidenceFileType::tryFrom($value) : null;

            if ($type instanceof EvidenceFileType) {
                $types[] = $type;
            }
        }

        return $types;
    }
}
