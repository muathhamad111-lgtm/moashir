<?php

declare(strict_types=1);

namespace App\Models\Entities;

use App\Data\Entities\ProfileChangeFileSet;
use App\Enums\Entities\EntityProfileField;
use App\Enums\Entities\ProfileChangeFile;
use App\Enums\Entities\ProfileChangeStatus;
use App\Models\Concerns\HasPublicId;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An entity's own profile edits waiting for staff approval: proposed values, the approved values they replace, and
 * the logo, VAT certificate, license certificate or authorization letter the entity uploaded or removed.
 *
 * @property int $id
 * @property string $public_id
 * @property int $entity_id
 * @property int|null $submitted_by_user_id
 * @property ProfileChangeStatus $status
 * @property int $revision
 * @property array<string, mixed> $proposed
 * @property array<string, mixed> $base_values
 * @property list<string> $changed_fields
 * @property int|null $pending_logo_file_id
 * @property bool $removes_logo
 * @property int|null $pending_vat_certificate_file_id
 * @property bool $removes_vat_certificate
 * @property int|null $pending_license_certificate_file_id
 * @property bool $removes_license_certificate
 * @property int|null $pending_authorization_letter_file_id
 * @property bool $removes_authorization_letter
 * @property array<string, string>|null $decisions
 * @property int|null $reviewed_by_user_id
 * @property string|null $review_note
 * @property Carbon|null $decided_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Entity $entity
 * @property-read User|null $submitter
 * @property-read User|null $reviewer
 * @property-read StoredFile|null $pendingLogo
 * @property-read StoredFile|null $pendingVatCertificate
 * @property-read StoredFile|null $pendingLicenseCertificate
 * @property-read StoredFile|null $pendingAuthorizationLetter
 */
final class EntityProfileChangeRequest extends Model
{
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = ['entity_id', 'submitted_by_user_id', 'status'];

    /**
     * @var list<string>
     */
    protected $hidden = ['proposed', 'base_values'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'revision' => 1,
        'removes_logo' => false,
        'removes_vat_certificate' => false,
        'removes_license_certificate' => false,
        'removes_authorization_letter' => false,
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'status' => ProfileChangeStatus::class,
            'revision' => 'integer',
            'proposed' => 'encrypted:array',
            'base_values' => 'encrypted:array',
            'changed_fields' => 'array',
            'pending_logo_file_id' => 'integer',
            'removes_logo' => 'boolean',
            'pending_vat_certificate_file_id' => 'integer',
            'removes_vat_certificate' => 'boolean',
            'pending_license_certificate_file_id' => 'integer',
            'removes_license_certificate' => 'boolean',
            'pending_authorization_letter_file_id' => 'integer',
            'removes_authorization_letter' => 'boolean',
            'decisions' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id')->withTrashed();
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function pendingLogo(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'pending_logo_file_id')->withTrashed();
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function pendingVatCertificate(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'pending_vat_certificate_file_id')->withTrashed();
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function pendingLicenseCertificate(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'pending_license_certificate_file_id')->withTrashed();
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function pendingAuthorizationLetter(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'pending_authorization_letter_file_id')->withTrashed();
    }

    public function isPending(): bool
    {
        return $this->status === ProfileChangeStatus::Pending;
    }

    /**
     * Replaces the proposed fields of one section: a field saved back to its approved value leaves the request.
     *
     * @param  list<EntityProfileField>  $sectionFields
     * @param  array<string, mixed>  $proposed
     * @param  array<string, mixed>  $approved
     */
    public function proposeFields(array $sectionFields, array $proposed, array $approved): void
    {
        $replaced = array_flip(array_map(static fn (EntityProfileField $field): string => $field->value, $sectionFields));
        $values = array_diff_key($this->proposed ?? [], $replaced);
        $base = array_diff_key($this->base_values ?? [], $replaced);

        foreach ($proposed as $field => $value) {
            if ($value !== ($approved[$field] ?? null)) {
                $values[$field] = $value;
                $base[$field] = $approved[$field] ?? null;
            }
        }

        $this->proposed = $values;
        $this->base_values = $base;
        $this->changed_fields = array_keys($values);
    }

    public function setFileChange(ProfileChangeFile $file, ?int $pendingFileId, bool $removes): void
    {
        $this->setAttribute($file->pendingColumn(), $pendingFileId);
        $this->setAttribute($file->removalColumn(), $removes);
    }

    public function fileChanges(): ProfileChangeFileSet
    {
        $slots = [];

        foreach (ProfileChangeFile::cases() as $file) {
            $pending = $this->getAttribute($file->pendingColumn());
            $slots[$file->value] = ['pending' => is_int($pending) ? $pending : null, 'removes' => $this->getAttribute($file->removalColumn()) === true];
        }

        return new ProfileChangeFileSet($slots);
    }

    /**
     * Every item the reviewer decides on: each changed field and each changed file.
     *
     * @return list<string>
     */
    public function items(): array
    {
        return [...($this->changed_fields ?? []), ...array_map(static fn (ProfileChangeFile $file): string => $file->value, $this->fileChanges()->changed())];
    }

    public function isEmpty(): bool
    {
        return $this->items() === [];
    }
}
