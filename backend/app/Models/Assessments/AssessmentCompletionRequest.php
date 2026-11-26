<?php

declare(strict_types=1);

namespace App\Models\Assessments;

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\CompletionExpiryOutcome;
use App\Enums\Assessments\CompletionRequestStatus;
use App\Models\Identity\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assessment_id
 * @property string $notes
 * @property list<array{sub_code?: string, main_code?: string, kind: string, note: string|null}> $items
 * @property Carbon $deadline_at
 * @property CompletionExpiryOutcome $expiry_outcome
 * @property CompletionRequestStatus $status
 * @property AdvancedAssessmentStatus|null $resumes_to
 * @property int|null $review_days_remaining
 * @property int $requested_by_user_id
 * @property Carbon|null $resolved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $requestedBy
 */
final class AssessmentCompletionRequest extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['assessment_id', 'notes', 'items', 'deadline_at', 'expiry_outcome', 'status', 'requested_by_user_id', 'resumes_to', 'review_days_remaining'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessment_id' => 'integer',
            'items' => 'array',
            'deadline_at' => 'datetime',
            'expiry_outcome' => CompletionExpiryOutcome::class,
            'status' => CompletionRequestStatus::class,
            'resumes_to' => AdvancedAssessmentStatus::class,
            'review_days_remaining' => 'integer',
            'requested_by_user_id' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id')->withTrashed();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', CompletionRequestStatus::Open);
    }

    public function hasExpired(CarbonInterface $now): bool
    {
        return $this->deadline_at->lessThan($now);
    }

    /**
     * @return list<string>
     */
    public function requestedSubCodes(): array
    {
        return array_values(array_unique(array_column($this->items, 'sub_code')));
    }

    /**
     * @param  string  $code  a main criterion or sub-criterion code; codes are unique across the levels of a version
     */
    public function requests(string $code, string $kind): bool
    {
        return array_any($this->items, static fn (array $item): bool => self::codeOf($item) === $code && $item['kind'] === $kind);
    }

    /**
     * The criterion an item names: a main criterion (its evidence) or a sub-criterion.
     *
     * @param  array{sub_code?: string, main_code?: string, kind: string, note: string|null}  $item
     */
    public static function codeOf(array $item): string
    {
        return $item['main_code'] ?? $item['sub_code'] ?? '';
    }
}
