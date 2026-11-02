<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Contracts\Search\SearchHit;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Support\Assessments\StatusMask;
use App\Support\Entities\EntityPresenter;

final readonly class RequestSearchHit implements SearchHit
{
    /**
     * @param  array<string, mixed>  $entity
     */
    public function __construct(
        public int $id,
        public string $number,
        public string $path,
        public string $status,
        public bool $isFrozen,
        public array $entity,
    ) {}

    public static function fromAssessment(Assessment $assessment, User $viewer): self
    {
        return new self(
            $assessment->id,
            $assessment->request_number,
            $assessment->path->value,
            StatusMask::statusFor($assessment, $viewer),
            $assessment->isFrozen(),
            EntityPresenter::summary($assessment->entity),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'path' => $this->path,
            'status' => $this->status,
            'is_frozen' => $this->isFrozen,
            'entity' => $this->entity,
        ];
    }
}
