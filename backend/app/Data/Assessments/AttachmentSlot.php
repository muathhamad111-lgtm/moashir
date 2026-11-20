<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Enums\Files\EvidenceFileType;

/**
 * One required or optional attachment of a criterion as the entity fills it. The file types and the file count it
 * takes always stay within the path's settings.
 */
final readonly class AttachmentSlot
{
    /**
     * @param  list<EvidenceFileType>  $allowedTypes  empty = every type of the path
     */
    public function __construct(public string $key, public bool $isRequired, public array $allowedTypes = [], public ?int $maxFiles = null) {}

    /**
     * The path types this slot accepts; the path's own types when the slot names none of them.
     *
     * @param  list<EvidenceFileType>  $pathTypes
     * @return list<EvidenceFileType>
     */
    public function typesWithin(array $pathTypes): array
    {
        $types = array_values(array_filter($pathTypes, fn (EvidenceFileType $type): bool => in_array($type, $this->allowedTypes, true)));

        return $types === [] ? $pathTypes : $types;
    }

    public function maxFilesWithin(int $pathMaximum): int
    {
        return min($this->maxFiles ?? $pathMaximum, $pathMaximum);
    }
}
