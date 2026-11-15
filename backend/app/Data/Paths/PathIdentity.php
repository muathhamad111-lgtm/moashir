<?php

declare(strict_types=1);

namespace App\Data\Paths;

use App\Enums\Assessments\AssessmentPath;
use App\Support\ValueObjects\LocalizedText;

final readonly class PathIdentity
{
    public function __construct(
        public AssessmentPath $path,
        public LocalizedText $name,
        public LocalizedText $description,
    ) {}

    /**
     * @return array{path: string, name: array{ar: string, en: string}, description: array{ar: string, en: string}}
     */
    public function toArray(): array
    {
        return ['path' => $this->path->value, 'name' => $this->name->toArray(), 'description' => $this->description->toArray()];
    }
}
