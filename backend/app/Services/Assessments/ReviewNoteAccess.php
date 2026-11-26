<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Enums\Identity\PermissionKey;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Identity\RequestPathScope;
use Illuminate\Auth\Access\Response;

final readonly class ReviewNoteAccess
{
    public function __construct(private RequestPathScope $paths) {}

    public function read(User $actor, Response $review): Response
    {
        if ($actor->isSuperAdmin()) {
            return Response::allow();
        }

        if ($review->denied()) {
            return $review;
        }

        return $actor->can(PermissionKey::RequestsNotesRead->value) ? Response::allow() : Response::deny();
    }

    public function write(User $actor, Assessment $assessment, Response $read): Response
    {
        if ($read->denied()) {
            return $read;
        }

        if (! $assessment->acceptsReviewNotes()) {
            return Response::deny();
        }

        if ($actor->isSuperAdmin()) {
            return Response::allow();
        }

        return $actor->can(PermissionKey::RequestsNotesWrite->value) && $this->actsOn($actor, $assessment) ? Response::allow() : Response::deny();
    }

    private function actsOn(User $actor, Assessment $assessment): bool
    {
        if ($actor->can(PermissionKey::RequestsQueueView->value) && $this->paths->covers($actor, $assessment->path)) {
            return true;
        }

        return $assessment->isActivelyAssignedTo($actor->id);
    }
}
