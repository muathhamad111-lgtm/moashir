<?php

declare(strict_types=1);

namespace App\Enums\Entities;

use App\Enums\Files\StoredFilePurpose;

/**
 * The official documents staff read on the entity's record: the license certificate and the authorization letter
 * given at registration, and the VAT registration certificate of the billing data.
 */
enum EntityDocument: string
{
    case LicenseCertificate = 'license_certificate';
    case AuthorizationLetter = 'authorization_letter';
    case VatCertificate = 'vat_certificate';

    public function column(): string
    {
        return $this->value.'_file_id';
    }

    public function purpose(): StoredFilePurpose
    {
        return match ($this) {
            self::LicenseCertificate => StoredFilePurpose::EntityLicenseCertificate,
            self::AuthorizationLetter => StoredFilePurpose::EntityAuthorizationLetter,
            self::VatCertificate => StoredFilePurpose::VatCertificate,
        };
    }

    public function profileChangeFile(): ProfileChangeFile
    {
        return match ($this) {
            self::LicenseCertificate => ProfileChangeFile::LicenseCertificate,
            self::AuthorizationLetter => ProfileChangeFile::AuthorizationLetter,
            self::VatCertificate => ProfileChangeFile::VatCertificate,
        };
    }

    public function maxKilobytesKey(): string
    {
        return 'moashir.entities.'.$this->value.'.max_kb';
    }

    /**
     * The documents every entity gives at registration; the VAT certificate only belongs to a VAT registration.
     *
     * @return list<self>
     */
    public static function required(): array
    {
        return [self::LicenseCertificate, self::AuthorizationLetter];
    }

    /**
     * @return list<string>
     */
    public static function requiredValues(): array
    {
        return array_map(static fn (self $document): string => $document->value, self::required());
    }
}
