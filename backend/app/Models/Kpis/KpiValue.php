<?php

declare(strict_types=1);

namespace App\Models\Kpis;

use App\Casts\KpiNumberCast;
use App\Support\ValueObjects\KpiNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $kpi_id
 * @property KpiNumber $value
 * @property Carbon $measured_on
 * @property int|null $recorded_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Kpi $kpi
 */
final class KpiValue extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['kpi_id', 'value', 'measured_on', 'recorded_by_user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => KpiNumberCast::class,
            'measured_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Kpi, $this>
     */
    public function kpi(): BelongsTo
    {
        return $this->belongsTo(Kpi::class);
    }
}
