<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The files an entity keeps on its record: the logo and its documents (VAT certificate, license certificate,
 * authorization letter).
 *
 * @mixin Entity
 */
trait HasEntityFiles
{
    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'logo_file_id');
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function vatCertificate(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'vat_certificate_file_id');
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function licenseCertificate(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'license_certificate_file_id');
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function authorizationLetter(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'authorization_letter_file_id');
    }
}
