<?php

declare(strict_types=1);

namespace App\Models\Entities;

use App\Casts\LocalizedTextCast;
use App\Support\ValueObjects\LocalizedText;
use Database\Factories\Entities\CityFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $region_id
 * @property string $code
 * @property LocalizedText $name
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Region $region
 */
final class City extends Model
{
    /** @use HasFactory<CityFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['region_id', 'code', 'name', 'sort_order', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'region_id' => 'integer',
            'name' => LocalizedTextCast::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
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
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
