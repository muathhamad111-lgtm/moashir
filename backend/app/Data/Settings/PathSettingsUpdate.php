<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Enums\Files\EvidenceFileType;
use App\Enums\Settings\PathEntityText;
use App\Enums\Settings\PathSetting;
use App\Support\ValueObjects\LocalizedText;
use App\Support\ValueObjects\Money;

final readonly class PathSettingsUpdate
{
    public const string PRICE = 'price';

    /**
     * @param  list<EvidenceFileType>|null  $evidenceTypes
     * @param  array<value-of<PathEntityText>, LocalizedText|null>  $textChanges
     */
    public function __construct(
        public ?int $reviewSlaDays = null,
        public ?int $maxFileMegabytes = null,
        public ?array $evidenceTypes = null,
        public ?int $maxFilesPerSub = null,
        public ?Money $price = null,
        public array $textChanges = [],
        public ?string $reason = null,
    ) {}

    /**
     * @return array<value-of<PathSetting>, int|list<string>>
     */
    public function scalarValues(): array
    {
        return array_filter([
            PathSetting::ReviewSlaDays->value => $this->reviewSlaDays,
            PathSetting::UploadMaxFileMb->value => $this->maxFileMegabytes,
            PathSetting::EvidenceAllowedTypes->value => $this->evidenceTypes === null
                ? null
                : array_map(static fn (EvidenceFileType $type): string => $type->value, $this->evidenceTypes),
            PathSetting::EvidenceMaxFilesPerSub->value => $this->maxFilesPerSub,
        ], static fn (int|array|null $value): bool => $value !== null);
    }

    public function isEmpty(): bool
    {
        return $this->scalarValues() === [] && ! $this->price instanceof Money && $this->textChanges === [];
    }
}
