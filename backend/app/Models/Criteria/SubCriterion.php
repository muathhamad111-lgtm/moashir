<?php

declare(strict_types=1);

namespace App\Models\Criteria;

use App\Casts\LocalizedTextCast;
use App\Casts\WeightCast;
use App\Enums\Criteria\EvaluationType;
use App\Support\ValueObjects\LocalizedText;
use App\Support\ValueObjects\Weight;
use Database\Factories\Criteria\SubCriterionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $main_criterion_id
 * @property string $code
 * @property LocalizedText $name
 * @property LocalizedText|null $description
 * @property Weight $weight
 * @property bool $distribute_equally
 * @property EvaluationType $evaluation_type
 * @property bool $has_cv
 * @property LocalizedText|null $assessor_note_label
 * @property bool $is_active
 * @property int $sort_order
 * @property int|null $cloned_from_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read MainCriterion $mainCriterion
 * @property-read Collection<int, GuidingPoint> $guidingPoints
 * @property-read Collection<int, AttachmentRequirement> $attachmentRequirements
 */
final class SubCriterion extends Model
{
    /** @use HasFactory<SubCriterionFactory> */
    use HasFactory;

    protected $table = 'sub_criteria';

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'description', 'weight', 'distribute_equally', 'evaluation_type', 'has_cv', 'assessor_note_label', 'is_active'];

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
            'evaluation_type' => EvaluationType::class,
            'has_cv' => 'boolean',
            'assessor_note_label' => LocalizedTextCast::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MainCriterion, $this>
     */
    public function mainCriterion(): BelongsTo
    {
        return $this->belongsTo(MainCriterion::class);
    }

    /**
     * @return HasMany<GuidingPoint, $this>
     */
    public function guidingPoints(): HasMany
    {
        return $this->hasMany(GuidingPoint::class)->orderBy('sort_order')->chaperone();
    }

    /**
     * @return HasMany<AttachmentRequirement, $this>
     */
    public function attachmentRequirements(): HasMany
    {
        return $this->hasMany(AttachmentRequirement::class)->orderBy('sort_order');
    }

    public function scoresGuidingPoints(): bool
    {
        return $this->evaluation_type->scoresGuidingPoints();
    }
}
