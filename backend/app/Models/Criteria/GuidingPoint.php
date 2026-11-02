<?php

declare(strict_types=1);

namespace App\Models\Criteria;

use App\Casts\LocalizedTextCast;
use App\Casts\WeightCast;
use App\Support\ValueObjects\LocalizedText;
use App\Support\ValueObjects\Weight;
use Database\Factories\Criteria\GuidingPointFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sub_criterion_id
 * @property string $code
 * @property LocalizedText $description
 * @property Weight $weight
 * @property bool $is_active
 * @property int $sort_order
 * @property int|null $cloned_from_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read SubCriterion $subCriterion
 * @property-read Collection<int, AttachmentRequirement> $attachmentRequirements
 */
final class GuidingPoint extends Model
{
    /** @use HasFactory<GuidingPointFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['description', 'weight', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'description' => LocalizedTextCast::class,
            'weight' => WeightCast::class,
            'is_active' => 'boolean',
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
     * @return HasMany<AttachmentRequirement, $this>
     */
    public function attachmentRequirements(): HasMany
    {
        return $this->hasMany(AttachmentRequirement::class)->orderBy('sort_order');
    }
}
