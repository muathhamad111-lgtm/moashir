<?php

declare(strict_types=1);

namespace App\Models\Criteria;

use App\Casts\LocalizedTextCast;
use App\Casts\WeightCast;
use App\Enums\Criteria\CriterionGroup;
use App\Support\ValueObjects\LocalizedText;
use App\Support\ValueObjects\Weight;
use Database\Factories\Criteria\MainCriterionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $criteria_version_id
 * @property string $code
 * @property LocalizedText $name
 * @property LocalizedText|null $description
 * @property Weight $weight
 * @property bool $distribute_equally
 * @property CriterionGroup $group
 * @property bool $is_active
 * @property int $sort_order
 * @property int|null $cloned_from_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read CriteriaVersion $criteriaVersion
 * @property-read Collection<int, SubCriterion> $subCriteria
 */
final class MainCriterion extends Model
{
    /** @use HasFactory<MainCriterionFactory> */
    use HasFactory;

    protected $table = 'main_criteria';

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'description', 'weight', 'distribute_equally', 'group', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => LocalizedTextCast::class,
            'description' => LocalizedTextCast::class,
            'weight' => WeightCast::class,
            'distribute_equally' => 'boolean',
            'group' => CriterionGroup::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CriteriaVersion, $this>
     */
    public function criteriaVersion(): BelongsTo
    {
        return $this->belongsTo(CriteriaVersion::class);
    }

    /**
     * @return HasMany<SubCriterion, $this>
     */
    public function subCriteria(): HasMany
    {
        return $this->hasMany(SubCriterion::class)->orderBy('sort_order')->chaperone();
    }
}
