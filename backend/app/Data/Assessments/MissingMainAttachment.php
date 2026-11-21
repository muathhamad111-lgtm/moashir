<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class MissingMainAttachment
{
    public function __construct(public int $mainId, public string $mainCode, public string $key) {}

    /**
     * @return array{main_criterion_id: int, code: string, attachment_key: string}
     */
    public function toArray(): array
    {
        return ['main_criterion_id' => $this->mainId, 'code' => $this->mainCode, 'attachment_key' => $this->key];
    }
}
