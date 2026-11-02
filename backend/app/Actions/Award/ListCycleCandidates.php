<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Enums\Award\CandidateRole;
use App\Models\Identity\User;
use App\Services\Award\CycleStaffEligibility;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Contracts\Config\Repository;

final readonly class ListCycleCandidates
{
    public function __construct(private CycleStaffEligibility $eligibility, private Repository $config) {}

    /**
     * @return list<User>
     */
    public function handle(CandidateRole $role, ?SearchTerm $search): array
    {
        $query = $this->eligibility->candidates($role)->orderBy('name')->orderBy('id');

        if ($search instanceof SearchTerm) {
            $query->matchingSearch($search);
        }

        return array_values($query->limit($this->config->integer('moashir.award.candidates_limit'))->get()->all());
    }
}
