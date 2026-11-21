<?php

declare(strict_types=1);

namespace App\Models\Criteria;

use App\Casts\LocalizedTextCast;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $main_criterion_id
 * @property int|null $sub_criterion_id
 * @property int|null $guiding_point_id
 * @property string $key
 * @property LocalizedText $name
 * @property LocalizedText|null $guidance
 * @property list<string>|null $allowed_types
 * @property int|null $max_files
 * @property bool $is_required
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read MainCriterion|null $mainCriterion
 * @property-read SubCriterion|null $subCriterion
 * @property-read GuidingPoint|null $guidingPoint
 */
final class AttachmentRequirement extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['key', 'name', 'guidance', 'allowed_types', 'max_files', 'is_required', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => LocalizedTextCast::class,
            'guidance' => LocalizedTextCast::class,
            'allowed_types' => 'array',
            'max_files' => 'integer',
            'is_required' => 'boolean',
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
     * @return BelongsTo<SubCriterion, $this>
     */
    public function subCriterion(): BelongsTo
    {
        return $this->belongsTo(SubCriterion::class);
    }

    /**
     * @return BelongsTo<GuidingPoint, $this>
     */
    public function guidingPoint(): BelongsTo
    {
        return $this->belongsTo(GuidingPoint::class);
    }
}
