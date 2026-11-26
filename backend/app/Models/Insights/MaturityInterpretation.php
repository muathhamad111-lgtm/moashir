<?php

declare(strict_types=1);

namespace App\Models\Insights;

use App\Casts\LocalizedTextCast;
use App\Enums\Insights\MaturityBand;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property MaturityBand $band
 * @property string|null $main_criterion_code
 * @property LocalizedText|null $text
 * @property int|null $updated_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class MaturityInterpretation extends Model
{
    private const string BAND_ORDER = "array_position(ARRAY['emerging', 'developing', 'mature', 'leading'], band::text)";

    /**
     * @var list<string>
     */
    protected $fillable = ['band', 'main_criterion_code', 'text'];

    /**
     * @var list<string>
     */
    protected $hidden = ['updated_by_user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'band' => MaturityBand::class,
            'text' => LocalizedTextCast::class,
            'updated_by_user_id' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderByRaw('main_criterion_code IS NOT NULL')
            ->orderBy('main_criterion_code')
            ->orderByRaw(self::BAND_ORDER);
    }

    public function isGeneric(): bool
    {
        return $this->main_criterion_code === null;
    }
}
