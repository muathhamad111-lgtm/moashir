<?php

declare(strict_types=1);

namespace App\Models\Assessments;

use App\Enums\Periods\PeriodCadence;
use App\Support\Periods\PeriodLabel;
use App\Support\ValueObjects\LocalizedText;
use App\Support\ValueObjects\PeriodWindow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property PeriodCadence $cadence
 * @property string $key
 * @property int $year
 * @property int $sequence
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property bool $is_shortened
 * @property Carbon|null $notice_sent_at
 * @property list<int> $reminders_sent
 * @property CarbonImmutable|null $last_digest_on
 * @property Carbon|null $closed_at
 * @property Carbon|null $superseded_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class AssessmentPeriod extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['cadence', 'key', 'year', 'sequence', 'starts_on', 'ends_on', 'starts_at', 'ends_at', 'is_shortened'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cadence' => PeriodCadence::class,
            'year' => 'integer',
            'sequence' => 'integer',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'is_shortened' => 'boolean',
            'notice_sent_at' => 'datetime',
            'reminders_sent' => 'array',
            'last_digest_on' => 'immutable_date',
            'closed_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function attributesOf(PeriodWindow $window): array
    {
        return [
            'cadence' => $window->cadence,
            'key' => $window->key(),
            'year' => $window->year,
            'sequence' => $window->sequence,
            'starts_on' => $window->startsOn->toDateString(),
            'ends_on' => $window->endsOn->toDateString(),
            'starts_at' => $window->startsAt(),
            'ends_at' => $window->endsAt(),
            'is_shortened' => $window->isShortened(),
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function live(Builder $query): void
    {
        $query->whereNull('superseded_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function containingDay(Builder $query, CarbonImmutable $day): void
    {
        $query->where('starts_on', '<=', $day->toDateString())->where('ends_on', '>=', $day->toDateString());
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function startingAfter(Builder $query, CarbonImmutable $day): void
    {
        $query->where('starts_on', '>', $day->toDateString());
    }

    public function label(): LocalizedText
    {
        return PeriodLabel::of($this->cadence, $this->year, $this->sequence);
    }

    public function window(string $timezone): PeriodWindow
    {
        return new PeriodWindow(
            $this->cadence,
            $this->year,
            $this->sequence,
            CarbonImmutable::parse($this->starts_on->toDateString(), $timezone),
            CarbonImmutable::parse($this->ends_on->toDateString(), $timezone),
        );
    }

    public function noticeOn(int $noticeDays): CarbonImmutable
    {
        return $this->starts_on->subDays($noticeDays);
    }

    public function hasSentReminder(int $days): bool
    {
        return in_array($days, $this->reminders_sent, true);
    }
}
