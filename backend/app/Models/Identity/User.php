<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Identity\AssessorQualification;
use App\Enums\Identity\Locale;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\SessionGroup;
use App\Enums\Identity\ThemePreference;
use App\Enums\Identity\UserStatus;
use App\Models\Notifications\Notification;
use App\Observers\Identity\UserPhoneIndexObserver;
use App\Support\Search\SearchTextNormalizer;
use App\Support\ValueObjects\EmailAddress;
use App\Support\ValueObjects\SearchTerm;
use Database\Factories\Identity\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\RoutesNotifications;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $phone_index
 * @property Carbon|null $phone_verified_at
 * @property string|null $password
 * @property int|null $entity_id
 * @property bool $password_change_required
 * @property bool $is_super_admin
 * @property UserStatus $status
 * @property Locale $locale
 * @property ThemePreference $theme
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property bool $mfa_required
 * @property int $failed_login_attempts
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property Carbon|null $password_changed_at
 * @property Carbon|null $locked_until
 * @property Carbon|null $deactivated_at
 * @property string|null $deactivation_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, Role> $roles
 * @property-read Collection<int, UserAssessorQualification> $assessorQualifications
 * @property-read Collection<int, UserRequestPath> $requestPaths
 */
#[ObservedBy([UserPhoneIndexObserver::class])]
final class User extends Authenticatable implements Auditable, HasLocalePreference
{
    use AuditableTrait;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use RoutesNotifications;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    public const array ADMINISTRATION_RELATIONS = ['roles', 'assessorQualifications', 'requestPaths'];

    protected string $guard_name = 'web';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_super_admin' => false,
        'mfa_required' => false,
        'password_change_required' => false,
        'failed_login_attempts' => 0,
        'status' => 'active',
        'locale' => 'ar',
        'theme' => 'system',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'phone', 'locale', 'theme'];

    /**
     * @var list<string>
     */
    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'is_super_admin', 'phone_index'];

    /**
     * @var list<string>
     */
    protected array $auditExclude = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        'failed_login_attempts', 'last_login_at', 'last_login_ip', 'phone', 'phone_index',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone' => 'encrypted',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_change_required' => 'boolean',
            'is_super_admin' => 'boolean',
            'status' => UserStatus::class,
            'locale' => Locale::class,
            'theme' => ThemePreference::class,
            'two_factor_confirmed_at' => 'datetime',
            'mfa_required' => 'boolean',
            'failed_login_attempts' => 'integer',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'locked_until' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PasswordHistory, $this>
     */
    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class);
    }

    /**
     * @return HasMany<UserSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    /**
     * @return HasMany<UserInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class);
    }

    /**
     * @return HasMany<TermsAcceptance, $this>
     */
    public function termsAcceptances(): HasMany
    {
        return $this->hasMany(TermsAcceptance::class);
    }

    /**
     * @return HasMany<Notification, $this>
     */
    public function inAppNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * @return HasMany<UserAssessorQualification, $this>
     */
    public function assessorQualifications(): HasMany
    {
        return $this->hasMany(UserAssessorQualification::class);
    }

    /**
     * @return HasMany<UserRequestPath, $this>
     */
    public function requestPaths(): HasMany
    {
        return $this->hasMany(UserRequestPath::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function qualifiedFor(Builder $query, AssessorQualification $qualification): void
    {
        $query->whereHas('assessorQualifications', static fn (Builder $held): Builder => $held->where('qualification', $qualification));
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function followingPath(Builder $query, AssessmentPath $path): void
    {
        $query->where(static fn (Builder $scoped): Builder => $scoped
            ->whereDoesntHave('requestPaths')
            ->orWhereHas('requestPaths', static fn (Builder $granted): Builder => $granted->where('path', $path)));
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withoutSuperAdmins(Builder $query): void
    {
        $query->where('is_super_admin', false);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function superAdmins(Builder $query): void
    {
        $query->where('is_super_admin', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function staff(Builder $query): void
    {
        $query->whereNull('entity_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function matchingSearch(Builder $query, SearchTerm $term): void
    {
        $pattern = $term->containsPattern();

        $query->where(static fn (Builder $inner): Builder => $inner
            ->whereRaw(SearchTextNormalizer::DATABASE_FUNCTION.'(name) like ?', [$pattern])
            ->orWhereRaw('lower(email) like ?', [$pattern]));
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withEmail(Builder $query, EmailAddress $email): void
    {
        $query->whereRaw('lower(email) = ?', [$email->value]);
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }

    public function isEntityUser(): bool
    {
        return $this->entity_id !== null || in_array(RoleKey::Entity, $this->roleKeys(), true);
    }

    /**
     * @return list<RoleKey>
     */
    public function roleKeys(): array
    {
        return array_values($this->roles
            ->map(static fn (Role $role): ?RoleKey => RoleKey::tryFrom($role->name))
            ->filter()
            ->all());
    }

    /**
     * @return list<AssessorQualification>
     */
    public function qualifications(): array
    {
        $held = $this->loadMissing('assessorQualifications')->assessorQualifications
            ->map(static fn (UserAssessorQualification $granted): AssessorQualification => $granted->qualification)
            ->all();

        return array_values(array_filter(AssessorQualification::cases(), static fn (AssessorQualification $qualification): bool => in_array($qualification, $held, true)));
    }

    public function isQualifiedFor(AssessorQualification $qualification): bool
    {
        return in_array($qualification, $this->qualifications(), true);
    }

    /**
     * @return list<AssessmentPath>
     */
    public function grantedRequestPaths(): array
    {
        return array_values($this->loadMissing('requestPaths')->requestPaths
            ->map(static fn (UserRequestPath $granted): AssessmentPath => $granted->path)
            ->all());
    }

    public function requiresMfa(): bool
    {
        return $this->isSuperAdmin() || $this->mfa_required;
    }

    public function mayUseSignInCode(): bool
    {
        return $this->isEntityUser() && ! $this->requiresMfa();
    }

    public function hasConfirmedTotp(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function effectiveStatus(): UserStatus
    {
        return $this->status === UserStatus::Active && $this->isLocked() ? UserStatus::Locked : $this->status;
    }

    public function sessionGroup(): SessionGroup
    {
        return match (true) {
            $this->isSuperAdmin() => SessionGroup::SuperAdmin,
            $this->isEntityUser() => SessionGroup::Entity,
            default => SessionGroup::Staff,
        };
    }

    public function preferredLocale(): string
    {
        return $this->locale->value;
    }

    public function routeNotificationForSms(): ?string
    {
        return $this->phone;
    }
}
