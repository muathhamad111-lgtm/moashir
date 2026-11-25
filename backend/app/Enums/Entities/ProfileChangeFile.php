<?php

declare(strict_types=1);

namespace App\Enums\Entities;

/**
 * The files an entity can change through a profile change request, each decided as one item.
 */
enum ProfileChangeFile: string
{
    case Logo = 'logo';
    case VatCertificate = 'vat_certificate';
    case LicenseCertificate = 'license_certificate';
    case AuthorizationLetter = 'authorization_letter';

    public function pendingColumn(): string
    {
        return 'pending_'.$this->value.'_file_id';
    }

    public function removalColumn(): string
    {
        return 'removes_'.$this->value;
    }

    public function entityColumn(): string
    {
        return $this->value.'_file_id';
    }

    /**
     * The relation to the file waiting for approval, on the change request.
     */
    public function pendingRelation(): string
    {
        return match ($this) {
            self::Logo => 'pendingLogo',
            self::VatCertificate => 'pendingVatCertificate',
            self::LicenseCertificate => 'pendingLicenseCertificate',
            self::AuthorizationLetter => 'pendingAuthorizationLetter',
        };
    }

    /**
     * The relation to the approved file it replaces, on the entity.
     */
    public function approvedRelation(): string
    {
        return match ($this) {
            self::Logo => 'logo',
            self::VatCertificate => 'vatCertificate',
            self::LicenseCertificate => 'licenseCertificate',
            self::AuthorizationLetter => 'authorizationLetter',
        };
    }

    public function section(): ?ProfileSection
    {
        return match ($this) {
            self::Logo => null,
            self::VatCertificate => ProfileSection::Billing,
            self::LicenseCertificate => ProfileSection::Identity,
            self::AuthorizationLetter => ProfileSection::Contact,
        };
    }

    public function labelKey(): string
    {
        return 'entities.profile_fields.'.$this->value;
    }

    /**
     * @return list<string>
     */
    public static function pendingRelations(): array
    {
        return array_map(static fn (self $file): string => $file->pendingRelation(), self::cases());
    }

    /**
     * @return list<string>
     */
    public static function approvedRelations(): array
    {
        return array_map(static fn (self $file): string => $file->approvedRelation(), self::cases());
    }
}
