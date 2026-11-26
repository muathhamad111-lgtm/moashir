<?php

declare(strict_types=1);

namespace App\Enums\Entities;

/**
 * One kind of event in an entity's account timeline (the «سجل الحساب» tab of the staff record).
 */
enum EntityTimelineKind: string
{
    case Created = 'created';
    case VerificationReminded = 'verification_reminded';
    case Verified = 'verified';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ProfileCompleted = 'profile_completed';
    case MarketingConsented = 'marketing_consented';
    case MarketingUnsubscribed = 'marketing_unsubscribed';
    case Deactivated = 'deactivated';
    case Restored = 'restored';
    case Deleted = 'deleted';
    case ProfileFieldChanged = 'profile_field_changed';
    case ProfileChangeRequested = 'profile_change_requested';
    case ProfileChangeDecided = 'profile_change_decided';
    case EmailChangeRequested = 'email_change_requested';
    case EmailChangeDecided = 'email_change_decided';

    /**
     * The account events read from the entity's own dates, keyed by the column that holds each date.
     *
     * @return array<string, self>
     */
    public static function lifecycleColumns(): array
    {
        return [
            'created_at' => self::Created,
            'verification_reminded_at' => self::VerificationReminded,
            'verified_at' => self::Verified,
            'approved_at' => self::Approved,
            'rejected_at' => self::Rejected,
            'profile_completed_at' => self::ProfileCompleted,
            'marketing_consent_at' => self::MarketingConsented,
            'marketing_unsubscribed_at' => self::MarketingUnsubscribed,
            'deactivated_at' => self::Deactivated,
            'restored_at' => self::Restored,
            'deleted_at' => self::Deleted,
        ];
    }

    /**
     * The entity column holding the reason staff gave for this event, when it has one.
     */
    public function reasonColumn(): ?string
    {
        return match ($this) {
            self::Rejected => 'rejection_reason',
            self::Deactivated => 'deactivation_reason',
            self::Deleted => 'deletion_reason',
            default => null,
        };
    }

    /**
     * The order of events that share a timestamp: an account is created before it is verified, a request is
     * submitted before it is decided. The feed lists the higher sequence first, as it does the newer date.
     */
    public function sequence(): int
    {
        return (int) array_search($this, self::cases(), true);
    }

    public function isLifecycle(): bool
    {
        return in_array($this, self::lifecycleColumns(), true);
    }
}
