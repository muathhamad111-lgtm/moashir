<?php

declare(strict_types=1);

namespace App\Models\Kpis;

use App\Casts\LocalizedContentCast;
use App\Support\ValueObjects\LocalizedContent;
use Database\Factories\Kpis\KpiCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property LocalizedContent $name
 * @property LocalizedContent|null $description
 * @property string|null $icon
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
final class KpiCategory extends Model
{
    /** @use HasFactory<KpiCategoryFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = ['key', 'name', 'description', 'icon', 'sort_order', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => LocalizedContentCast::class,
            'description' => LocalizedContentCast::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Kpi, $this>
     */
    public function kpis(): HasMany
    {
        return $this->hasMany(Kpi::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function listed(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }
}
