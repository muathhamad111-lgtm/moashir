<?php

declare(strict_types=1);

namespace App\Models\Criteria;

use App\Casts\LocalizedTextCast;
use App\Enums\Criteria\CriteriaVersionStatus;
use App\Enums\Criteria\ScaleKind;
use App\Support\ValueObjects\LocalizedText;
use Database\Factories\Criteria\CriteriaVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $version_number
 * @property LocalizedText|null $label
 * @property CriteriaVersionStatus $status
 * @property ScaleKind|null $scale_kind
 * @property list<array<string, mixed>>|null $validation_errors
 * @property int|null $cloned_from_version_id
 * @property int|null $published_by_user_id
 * @property Carbon|null $published_at
 * @property Carbon|null $archived_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, MainCriterion> $mainCriteria
 */
final class CriteriaVersion extends Model
{
    /** @use HasFactory<CriteriaVersionFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = ['version_number', 'label', 'cloned_from_version_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'label' => LocalizedTextCast::class,
            'status' => CriteriaVersionStatus::class,
            'scale_kind' => ScaleKind::class,
            'validation_errors' => 'array',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<MainCriterion, $this>
     */
    public function mainCriteria(): HasMany
    {
        return $this->hasMany(MainCriterion::class)->orderBy('sort_order')->chaperone();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function draft(Builder $query): void
    {
        $query->where('status', CriteriaVersionStatus::Draft);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', CriteriaVersionStatus::Published);
    }

    public function isDraft(): bool
    {
        return $this->status === CriteriaVersionStatus::Draft;
    }

    public function isConsistent(): bool
    {
        return $this->validation_errors === null || $this->validation_errors === [];
    }
}
