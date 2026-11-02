<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Data\Criteria\AttachmentRequirementData;
use App\Data\Criteria\CriteriaSeed;
use App\Data\Criteria\CriteriaSeedMain;
use App\Data\Criteria\CriteriaSeedSub;
use App\Data\Criteria\GuidingPointAttributes;
use App\Data\Criteria\MainCriterionAttributes;
use App\Data\Criteria\SubCriterionAttributes;
use App\Enums\Criteria\CriterionGroup;
use App\Enums\Criteria\EvaluationType;
use App\Support\ScalarValue;
use App\Support\ValueObjects\LocalizedText;
use App\Support\ValueObjects\Weight;
use InvalidArgumentException;

final class CriteriaSeedSource
{
    public function load(string $path): CriteriaSeed
    {
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new InvalidArgumentException('The criteria seed file is missing.');
        }

        $data = $this->record(json_decode($contents, true, 32, JSON_THROW_ON_ERROR));

        return new CriteriaSeed(
            $this->text($data, 'label'),
            array_map($this->main(...), $this->records($data, 'mains')),
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function main(array $data): CriteriaSeedMain
    {
        return new CriteriaSeedMain(
            new MainCriterionAttributes(
                $this->text($data, 'name'),
                $this->optionalText($data, 'description'),
                Weight::of(ScalarValue::string($data['weight'] ?? null)),
                ($data['distribute_equally'] ?? false) === true,
                CriterionGroup::from(ScalarValue::string($data['group'] ?? null)),
            ),
            array_map($this->sub(...), $this->records($data, 'subs')),
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function sub(array $data): CriteriaSeedSub
    {
        return new CriteriaSeedSub(
            new SubCriterionAttributes(
                $this->text($data, 'name'),
                $this->optionalText($data, 'description'),
                Weight::of(ScalarValue::string($data['weight'] ?? null)),
                ($data['distribute_equally'] ?? false) === true,
                EvaluationType::from(ScalarValue::string($data['evaluation_type'] ?? null)),
                ($data['has_cv'] ?? true) === true,
                $this->optionalText($data, 'assessor_note_label'),
                array_map($this->attachment(...), $this->records($data, 'attachments')),
            ),
            array_map($this->point(...), $this->records($data, 'points')),
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function point(array $data): GuidingPointAttributes
    {
        return new GuidingPointAttributes(
            $this->text($data, 'description'),
            Weight::of(ScalarValue::string($data['weight'] ?? null)),
            array_map($this->attachment(...), $this->records($data, 'attachments')),
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function attachment(array $data): AttachmentRequirementData
    {
        return new AttachmentRequirementData(null, $this->text($data, 'name'), ($data['required'] ?? true) === true);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function text(array $data, string $key): LocalizedText
    {
        return LocalizedText::fromArray($this->record($data[$key] ?? null));
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function optionalText(array $data, string $key): ?LocalizedText
    {
        return isset($data[$key]) ? $this->text($data, $key) : null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<array<array-key, mixed>>
     */
    private function records(array $data, string $key): array
    {
        $items = $data[$key] ?? [];

        return is_array($items) ? array_values(array_map($this->record(...), $items)) : [];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function record(mixed $value): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('The criteria seed file is malformed.');
        }

        return $value;
    }
}
