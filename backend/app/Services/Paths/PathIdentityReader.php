<?php

declare(strict_types=1);

namespace App\Services\Paths;

use App\Data\Paths\PathIdentity;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Entities\EntityRequestPath;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Settings\PathIdentityField;
use App\Services\Settings\SettingsStore;
use App\Support\ValueObjects\LocalizedText;

/**
 * The single source of the name and description of every assessment path. The name is the fixed translation
 * (operator decision: path names are not editable); the description is the value the administrator saved in
 * «إدارة المسارات», or the translation default when none was saved.
 */
final readonly class PathIdentityReader
{
    private const string NAMES = 'assessments.paths.';

    public function __construct(private SettingsStore $settings, private PathIdentityCache $cache) {}

    /**
     * @return list<PathIdentity>
     */
    public function all(): array
    {
        return array_map($this->of(...), AssessmentPath::cases());
    }

    public function of(AssessmentPath $path): PathIdentity
    {
        return new PathIdentity($path, $this->nameOf($path), $this->descriptionOf($path));
    }

    public function nameOf(AssessmentPath $path): LocalizedText
    {
        return LocalizedText::fromTranslation(self::NAMES.$path->value);
    }

    public function labelOf(EntityRequestPath $requestPath): LocalizedText
    {
        return LocalizedText::fromTranslation(self::NAMES.$requestPath->value);
    }

    public function purposeLabelOf(PaymentPurpose $purpose): LocalizedText
    {
        return match ($purpose) {
            PaymentPurpose::SelfAssessment => $this->nameOf(AssessmentPath::SelfAssessment),
            PaymentPurpose::Accredited => $this->nameOf(AssessmentPath::Accredited),
            PaymentPurpose::Award, PaymentPurpose::Bag => LocalizedText::fromTranslation('finance.purposes.'.$purpose->value),
        };
    }

    private function descriptionOf(AssessmentPath $path): LocalizedText
    {
        $key = PathIdentityField::Description->keyFor($path);
        $stored = $this->stored()[$key->value] ?? null;
        $default = $key->defaultValue();

        return LocalizedText::fromArray(is_array($stored) && $this->isComplete($stored) ? $stored : (is_array($default) ? $default : []));
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    private function isComplete(array $value): bool
    {
        return is_string($value['ar'] ?? null) && trim($value['ar']) !== '' && is_string($value['en'] ?? null) && trim($value['en']) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        return $this->cache->remember(fn (): array => $this->settings->stored(PathIdentityField::allKeys()));
    }
}
