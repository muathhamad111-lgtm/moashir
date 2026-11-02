<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class MissingAttachment
{
    public function __construct(public int $subId, public string $subCode, public string $key) {}

    /**
     * @return array{sub_criterion_id: int, code: string, attachment_key: string}
     */
    public function toArray(): array
    {
        return ['sub_criterion_id' => $this->subId, 'code' => $this->subCode, 'attachment_key' => $this->key];
    }
}
