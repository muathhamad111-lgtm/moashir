<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Enums\Assessments\ReviewNoteAuthorRole;

final readonly class ReviewNoteStamp
{
    public function __construct(public int $assessmentId, public int $authorId, public ReviewNoteAuthorRole $role, public ?string $reason) {}
}
