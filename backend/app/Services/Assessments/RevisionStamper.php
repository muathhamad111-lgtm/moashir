<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\RevisionStamp;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Support\RequestContext;

final readonly class RevisionStamper
{
    public function __construct(private RequestContext $context, private CurrentCompletionRound $rounds) {}

    public function stampFor(User $author, Assessment $assessment): RevisionStamp
    {
        return new RevisionStamp($author->id, $this->context->reason(), $this->rounds->idFor($assessment));
    }
}
