<?php

declare(strict_types=1);

namespace App\Models\Entities;

use App\Casts\EntityNameCast;
use App\Data\Entities\EntityProfileData;
use App\Enums\Certificates\CertificateKind;
use App\Enums\Certificates\CertificateStatus;
use App\Enums\Entities\DevelopmentSector;
use App\Enums\Entities\EntityProfileField;
use App\Enums\Entities\EntityStatus;
use App\Enums\Entities\EntityType;
use App\Enums\Entities\OrgSize;
use App\Models\Assessments\Assessment;
use App\Models\Certificates\Certificate;
use App\Models\Files\StoredFile;
use App\Models\Identity\EmailChangeRequest;
use App\Models\Identity\User;
use App\Support\Entities\ProfileFieldValues;
use App\Support\Periods\LocalDay;
use App\Support\Search\SearchTextNormalizer;
use App\Support\ValueObjects\EntityName;
use App\Support\ValueObjects\ProfileCompletion;
use App\Support\ValueObjects\SearchTerm;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\Entities\EntityFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * @property int $id
 * @property string $public_id
 * @property EntityName $name
 * @property EntityType $entity_type
 * @property DevelopmentSector $development_sector
 * @property OrgSize $org_size
 * @property int $founding_year
 * @property int $region_id
 * @property int $city_id
 * @property string|null $governance_grade
 * @property int|null $employees_count
 * @property int|null $annual_beneficiaries_count
 * @property bool|null $has_strategic_plan
 * @property string|null $unified_number
 * @property string|null $license_number
 * @property CarbonImmutable|null $founded_on
 * @property string|null $website_url
 * @property int|null $logo_file_id
 * @property EntityStatus $status
 * @property Carbon|null $verified_at
 * @property Carbon|null $profile_completed_at
 * @property Carbon|null $deactivated_at
 * @property string|null $deactivation_reason
 * @property Carbon|null $marketing_consent_at
 * @property Carbon|null $marketing_unsubscribed_at
 * @property Carbon|null $verification_reminded_at
 * @property int|null $deleted_by_user_id
 * @property string|null $deletion_reason
 * @property Carbon|null $restored_at
 * @property Carbon|null $anonymized_at
 * @property Carbon|null $deleted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Region $region
 * @property-read City $city
 * @property-read User|null $owner
 * @property-read StoredFile|null $logo
 * @property-read Certificate|null $levelCertificate
 * @property-read Certificate|null $lastLevelCertificate
 */
final class Entity extends Model
{
    /** @use HasFactory<EntityFactory> */
    use HasFactory;

    use SoftDeletes;

    public const string REFERENCE_PREFIX = 'ENT-';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending_verification',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name', 'entity_type', 'development_sector', 'org_size', 'founding_year', 'region_id', 'city_id',
        'governance_grade', 'employees_count', 'annual_beneficiaries_count', 'has_strategic_plan', 'license_number',
        'founded_on', 'website_url', 'unified_number',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => EntityNameCast::class,
            'entity_type' => EntityType::class,
            'development_sector' => DevelopmentSector::class,
            'org_size' => OrgSize::class,
            'founding_year' => 'integer',
            'region_id' => 'integer',
            'city_id' => 'integer',
            'governance_grade' => 'decimal:2',
            'employees_count' => 'integer',
            'annual_beneficiaries_count' => 'integer',
            'has_strategic_plan' => 'boolean',
            'founded_on' => 'immutable_date',
            'logo_file_id' => 'integer',
            'status' => EntityStatus::class,
            'verified_at' => 'datetime',
            'profile_completed_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'marketing_consent_at' => 'datetime',
            'marketing_unsubscribed_at' => 'datetime',
            'verification_reminded_at' => 'datetime',
            'restored_at' => 'datetime',
            'anonymized_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @return HasOne<User, $this>
     */
    public function owner(): HasOne
    {
        return $this->hasOne(User::class, 'entity_id');
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'logo_file_id');
    }

    /**
     * @return HasMany<EntityProfileChange, $this>
     */
    public function profileChanges(): HasMany
    {
        return $this->hasMany(EntityProfileChange::class);
    }

    /**
     * @return HasMany<EmailChangeRequest, $this>
     */
    public function emailChangeRequests(): HasMany
    {
        return $this->hasMany(EmailChangeRequest::class);
    }

    /**
     * @return HasMany<PrivacyRequest, $this>
     */
    public function privacyRequests(): HasMany
    {
        return $this->hasMany(PrivacyRequest::class);
    }

    /**
     * @return HasMany<Assessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /**
     * @return HasOne<Certificate, $this>
     */
    public function levelCertificate(): HasOne
    {
        return $this->hasOne(Certificate::class)->ofMany(
            ['issued_at' => 'max', 'id' => 'max'],
            static function (Builder $certificates): void {
                self::constrainToIssuedAccredited($certificates);
                $certificates->where('valid_until', '>=', LocalDay::today()->toDateString());
            },
        );
    }

    /**
     * @return HasOne<Certificate, $this>
     */
    public function lastLevelCertificate(): HasOne
    {
        return $this->hasOne(Certificate::class)->ofMany(
            ['issued_at' => 'max', 'id' => 'max'],
            static function (Builder $certificates): void {
                self::constrainToIssuedAccredited($certificates);
            },
        );
    }

    /**
     * @param  Builder<Certificate>  $certificates
     */
    private static function constrainToIssuedAccredited(Builder $certificates): void
    {
        $certificates->where('kind', CertificateKind::Accredited)->where('status', CertificateStatus::Issued);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function notErased(Builder $query): void
    {
        $query->whereNull('anonymized_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function matchingSearch(Builder $query, SearchTerm $term): void
    {
        $pattern = $term->containsPattern();
        $normalize = SearchTextNormalizer::DATABASE_FUNCTION;

        $query->where(static fn (Builder $inner): Builder => $inner
            ->whereRaw($normalize."(name->>'ar') like ?", [$pattern])
            ->orWhereRaw($normalize."(coalesce(name->>'en', '')) like ?", [$pattern])
            ->orWhereHas('owner', static fn (Builder $owner): Builder => $owner->whereRaw('lower(email) like ?', [$pattern])));
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function matchingLookup(Builder $query, SearchTerm $term): void
    {
        $id = self::idFromReference($term->value);

        $query->where(static fn (Builder $inner): Builder => $inner
            ->matchingSearch($term)
            ->when($id !== null, static fn (Builder $byReference): Builder => $byReference->orWhere('id', $id)));
    }

    public static function idFromReference(string $reference): ?int
    {
        if (preg_match('/^(?:ent-?)?0*(\d{1,15})$/i', trim($reference), $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    public function reference(): string
    {
        return sprintf('%s%06d', self::REFERENCE_PREFIX, $this->id);
    }

    public function isPendingVerification(): bool
    {
        return $this->status === EntityStatus::PendingVerification;
    }

    public function isActive(): bool
    {
        return $this->status === EntityStatus::Active;
    }

    public function isDeactivated(): bool
    {
        return $this->status === EntityStatus::Deactivated;
    }

    public function isErased(): bool
    {
        return $this->anonymized_at !== null;
    }

    public function hasCompleteProfile(): bool
    {
        return $this->profileCompletion()->isComplete();
    }

    public function profileCompletion(): ProfileCompletion
    {
        return ProfileCompletion::of(ProfileFieldValues::of($this));
    }

    public function syncProfileCompletedAt(): void
    {
        $this->profile_completed_at = $this->hasCompleteProfile() ? ($this->profile_completed_at ?? Date::now()) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function profileAttributesFrom(EntityProfileData $profile): array
    {
        return $this->withDerivedFoundingYear($profile->toAttributes());
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function withDerivedFoundingYear(array $attributes): array
    {
        $foundedOn = array_key_exists(EntityProfileField::FoundedOn->value, $attributes) ? $attributes[EntityProfileField::FoundedOn->value] : $this->founded_on;

        return $foundedOn instanceof CarbonInterface ? [...$attributes, EntityProfileField::FoundingYear->value => $foundedOn->year] : $attributes;
    }
}
