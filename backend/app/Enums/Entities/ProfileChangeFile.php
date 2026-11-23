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

    public function pendingColumn(): string
    {
        return match ($this) {
            self::Logo => 'pending_logo_file_id',
            self::VatCertificate => 'pending_vat_certificate_file_id',
        };
    }

    public function removalColumn(): string
    {
        return match ($this) {
            self::Logo => 'removes_logo',
            self::VatCertificate => 'removes_vat_certificate',
        };
    }

    public function labelKey(): string
    {
        return 'entities.profile_fields.'.$this->value;
    }
}
