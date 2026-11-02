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
 * @property int|null $sub_criterion_id
 * @property int|null $guiding_point_id
 * @property string $key
 * @property LocalizedText $name
 * @property bool $is_required
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read SubCriterion|null $subCriterion
 * @property-read GuidingPoint|null $guidingPoint
 */
final class AttachmentRequirement extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['key', 'name', 'is_required', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => LocalizedTextCast::class,
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
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
