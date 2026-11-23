<?php

declare(strict_types=1);

namespace App\Services\Nda;

use App\Data\Nda\NdaRegisterPage;
use App\Enums\Nda\NdaUserStatus;
use App\Models\Identity\TermsAcceptance;
use App\Models\Identity\User;
use App\Support\Nda\MaskedIpAddress;
use App\Support\Nda\NdaRoleNames;

/**
 * The rows of the acceptance register: one in-scope user each, with the acceptance of the current version when
 * there is one. The IP address is masked here; the audited export carries it in full.
 */
final class NdaRegisterPresenter
{
    /**
     * @return list<array<string, mixed>>
     */
    public function rows(NdaRegisterPage $page): array
    {
        return array_map(
            fn (User $user): array => $this->row($user, $page->acceptances[$user->id] ?? null, $page->lastAccepted[$user->id] ?? null),
            array_values($page->users->items()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $user, ?TermsAcceptance $acceptance, ?string $lastAccepted): array
    {
        return [
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'roles' => NdaRoleNames::of($user)],
            'status' => ($acceptance instanceof TermsAcceptance ? NdaUserStatus::Accepted : NdaUserStatus::Pending)->value,
            'last_accepted_version' => $lastAccepted,
            'accepted_at' => $acceptance?->created_at->toIso8601String(),
            'accepted_roles' => $acceptance->accepted_roles ?? [],
            'ip_masked' => MaskedIpAddress::of($acceptance?->ip_address),
        ];
    }
}
