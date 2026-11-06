<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Models\Contact\ContactMessage;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChange;
use App\Models\Entities\PrivacyRequest;
use App\Models\Identity\EmailChangeRequest;
use App\Models\Identity\TermsAcceptance;
use App\Models\Identity\User;
use App\Services\Contact\ContactMessageExporter;
use Illuminate\Support\Carbon;

final readonly class PersonalDataPackageBuilder
{
    public function __construct(private ContactMessageExporter $contactMessages) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Entity $entity): array
    {
        $entity->loadMissing(['region', 'city']);
        $owner = User::query()->where('entity_id', $entity->id)->first();

        return [
            'generated_at' => Carbon::now()->toIso8601String(),
            'entity' => $this->entity($entity),
            'account' => $owner instanceof User ? $this->account($owner) : null,
            'consents' => $this->consents($entity, $owner),
            'profile_changes' => $this->profileChanges($entity),
            'email_changes' => $this->emailChanges($entity),
            'privacy_requests' => $this->privacyRequests($entity),
            'contact_messages' => $this->contactMessagesOf($entity, $owner),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contactMessagesOf(Entity $entity, ?User $owner): array
    {
        $linked = ContactMessage::query()->where('entity_id', $entity->id);

        if ($owner instanceof User) {
            $linked->orWhere('user_id', $owner->id);
        }

        return $this->contactMessages->rows($linked);
    }

    /**
     * @return array<string, mixed>
     */
    private function entity(Entity $entity): array
    {
        return [
            'reference' => $entity->reference(),
            'name' => $entity->name->toArray(),
            'entity_type' => $entity->entity_type->value,
            'development_sector' => $entity->development_sector->value,
            'org_size' => $entity->org_size->value,
            'founding_year' => $entity->founding_year,
            'region' => $entity->region->name->toArray(),
            'city' => $entity->city->name->toArray(),
            'governance_grade' => $entity->governance_grade,
            'employees_count' => $entity->employees_count,
            'annual_beneficiaries_count' => $entity->annual_beneficiaries_count,
            'has_strategic_plan' => $entity->has_strategic_plan,
            'unified_number' => $entity->unified_number,
            'license_number' => $entity->license_number,
            'founded_on' => $entity->founded_on?->toDateString(),
            'website_url' => $entity->website_url,
            'has_logo' => $entity->logo_file_id !== null,
            'status' => $entity->status->value,
            'registered_at' => $entity->created_at->toIso8601String(),
            'verified_at' => $entity->verified_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function account(User $owner): array
    {
        return [
            'authorized_person_name' => $owner->name,
            'official_email' => $owner->email,
            'phone' => $owner->phone,
            'locale' => $owner->locale->value,
            'email_verified_at' => $owner->email_verified_at?->toIso8601String(),
            'last_login_at' => $owner->last_login_at?->toIso8601String(),
            'created_at' => $owner->created_at->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function consents(Entity $entity, ?User $owner): array
    {
        $acceptances = $owner instanceof User
            ? TermsAcceptance::query()->with('termsVersion')->where('user_id', $owner->id)->orderBy('id')->get()
                ->map(static fn (TermsAcceptance $acceptance): array => [
                    'kind' => $acceptance->termsVersion?->kind,
                    'version' => $acceptance->termsVersion?->version,
                    'accepted_at' => $acceptance->created_at?->toIso8601String(),
                ])->all()
            : [];

        return [
            'terms' => array_values($acceptances),
            'marketing_consent_at' => $entity->marketing_consent_at?->toIso8601String(),
            'marketing_unsubscribed_at' => $entity->marketing_unsubscribed_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function profileChanges(Entity $entity): array
    {
        return array_values(EntityProfileChange::query()->where('entity_id', $entity->id)->orderBy('id')->get()
            ->map(static fn (EntityProfileChange $change): array => [
                'field' => $change->field->value,
                'old_value' => $change->old_value,
                'new_value' => $change->new_value,
                'changed_at' => $change->created_at->toIso8601String(),
            ])->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function emailChanges(Entity $entity): array
    {
        return array_values(EmailChangeRequest::query()->where('entity_id', $entity->id)->orderBy('id')->get()
            ->map(static fn (EmailChangeRequest $request): array => [
                'old_email' => $request->old_email,
                'new_email' => $request->new_email,
                'status' => $request->status->value,
                'requested_at' => $request->created_at->toIso8601String(),
                'applied_at' => $request->applied_at?->toIso8601String(),
            ])->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function privacyRequests(Entity $entity): array
    {
        return array_values(PrivacyRequest::query()->where('entity_id', $entity->id)->orderBy('id')->get()
            ->map(static fn (PrivacyRequest $request): array => [
                'kind' => $request->kind->value,
                'status' => $request->status->value,
                'requested_at' => $request->created_at->toIso8601String(),
                'decided_at' => $request->decided_at?->toIso8601String(),
            ])->all());
    }
}
