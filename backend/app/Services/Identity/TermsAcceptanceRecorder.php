<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Identity\TermsAcceptance;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Support\Nda\NdaRoleNames;
use Illuminate\Http\Request;

final readonly class TermsAcceptanceRecorder
{
    public function __construct(private Request $request, private AuditRecorder $audit) {}

    /**
     * @param  list<int>  $termsVersionIds
     */
    public function accept(User $user, array $termsVersionIds): void
    {
        foreach (array_unique($termsVersionIds) as $termsVersionId) {
            $user->termsAcceptances()->create([
                'terms_version_id' => $termsVersionId,
                'ip_address' => $this->request->ip(),
                'user_agent' => $this->request->userAgent(),
            ]);
        }
    }

    /**
     * Records an agreement acceptance with the roles held at that moment, once per user and version: a repeated or
     * concurrent acceptance finds the existing row through the unique (user, version) constraint.
     */
    public function acceptNda(User $user, TermsVersion $version): TermsAcceptance
    {
        $acceptance = TermsAcceptance::query()->createOrFirst(
            ['user_id' => $user->id, 'terms_version_id' => $version->id],
            ['ip_address' => $this->request->ip(), 'user_agent' => $this->request->userAgent(), 'accepted_roles' => NdaRoleNames::of($user)],
        );

        if ($acceptance->wasRecentlyCreated) {
            $this->audit->record(new AuditEntry(AuditEvent::NdaAccepted, $acceptance, [], [
                'terms_version_id' => $version->id,
                'version' => $version->version,
                'accepted_roles' => $acceptance->accepted_roles,
            ]));
        }

        return $acceptance;
    }
}
