<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Enums\Assessments\AssessmentPath;
use App\Models\Identity\User;
use App\Services\Identity\RequestPathScope;

final readonly class QueuePathSelector
{
    public function __construct(private RequestPathScope $scope) {}

    /**
     * @return non-empty-list<AssessmentPath>
     */
    public function followedBy(mixed $user): array
    {
        $paths = $user instanceof User ? $this->scope->pathsFor($user) : [];

        return $paths === [] ? [AssessmentPath::Accredited] : $paths;
    }

    public function chosenBy(mixed $user, string $requested): AssessmentPath
    {
        $followed = $this->followedBy($user);
        $path = AssessmentPath::tryFrom($requested);

        return $path instanceof AssessmentPath && in_array($path, $followed, true) ? $path : $followed[0];
    }
}
