<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Enums\Files\EvidenceFileType;

final readonly class UploadLimits
{
    public const int MAX_FILE_MB = 100;

    public const int MAX_FILES_PER_SUB = 50;

    private const int KILOBYTES_PER_MEGABYTE = 1024;

    /**
     * @param  list<EvidenceFileType>  $types
     */
    public function __construct(public int $maxFileMegabytes, public array $types, public int $maxFilesPerSub) {}

    public function maxFileKilobytes(): int
    {
        return $this->maxFileMegabytes * self::KILOBYTES_PER_MEGABYTE;
    }

    /**
     * @return array{max_file_mb: int, allowed_types: list<string>, max_files_per_sub: int}
     */
    public function toArray(): array
    {
        return [
            'max_file_mb' => $this->maxFileMegabytes,
            'allowed_types' => array_map(static fn (EvidenceFileType $type): string => $type->value, $this->types),
            'max_files_per_sub' => $this->maxFilesPerSub,
        ];
    }
}
