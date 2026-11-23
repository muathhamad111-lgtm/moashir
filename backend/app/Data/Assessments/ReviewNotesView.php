<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Assessments\AssessmentReviewNote;

final readonly class ReviewNotesView
{
    /**
     * @param  list<AssessmentReviewNote>|null  $notes
     */
    public function __construct(public ?array $notes, public int $viewerId, public bool $revealsAuthors, public bool $canWrite) {}

    public static function hidden(int $viewerId): self
    {
        return new self(null, $viewerId, false, false);
    }

    public function isReadable(): bool
    {
        return $this->notes !== null;
    }
}
