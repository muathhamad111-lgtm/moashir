<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Enums\Assessments\ReviewNoteAuthorRole;
use App\Enums\Identity\RoleKey;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;

final class ReviewNoteAuthorRoleResolver
{
    public function roleOf(User $author, Assessment $assessment): ReviewNoteAuthorRole
    {
        return match (true) {
            $author->isSuperAdmin() => ReviewNoteAuthorRole::SuperAdmin,
            $assessment->isActivelyAssignedTo($author->id) => ReviewNoteAuthorRole::Assessor,
            in_array(RoleKey::RequestFollower, $author->loadMissing('roles')->roleKeys(), true) => ReviewNoteAuthorRole::Follower,
            default => ReviewNoteAuthorRole::Admin,
        };
    }
}
