<?php

declare(strict_types=1);

namespace App\Models\Assessments;

use App\Data\Assessments\ReviewNoteUnit;
use App\Enums\Assessments\ReviewNoteAuthorRole;
use App\Enums\Assessments\ReviewNoteLevel;
use App\Exceptions\Persistence\AppendOnlyViolation;
use App\Models\Identity\User;
use Database\Factories\Assessments\AssessmentReviewNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assessment_id
 * @property ReviewNoteLevel $level
 * @property int $main_criterion_id
 * @property int|null $sub_criterion_id
 * @property int|null $guiding_point_id
 * @property int $author_user_id
 * @property ReviewNoteAuthorRole $author_role
 * @property string $body
 * @property int $version
 * @property string|null $change_reason
 * @property Carbon|null $superseded_at
 * @property Carbon $created_at
 * @property-read User $author
 */
final class AssessmentReviewNote extends Model
{
    /** @use HasFactory<AssessmentReviewNoteFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'assessment_id', 'level', 'main_criterion_id', 'sub_criterion_id', 'guiding_point_id', 'author_user_id', 'author_role',
        'body', 'version', 'change_reason',
    ];

    protected static function booted(): void
    {
        self::updating(static function (self $note): void {
            if ($note->getOriginal('superseded_at') !== null || array_keys($note->getDirty()) !== ['superseded_at']) {
                throw AppendOnlyViolation::forModel(self::class);
            }
        });

        self::deleting(static fn (): never => throw AppendOnlyViolation::forModel(self::class));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessment_id' => 'integer',
            'level' => ReviewNoteLevel::class,
            'main_criterion_id' => 'integer',
            'sub_criterion_id' => 'integer',
            'guiding_point_id' => 'integer',
            'author_user_id' => 'integer',
            'author_role' => ReviewNoteAuthorRole::class,
            'version' => 'integer',
            'superseded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id')->withTrashed();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $query->whereNull('superseded_at');
    }

    public function unit(): ReviewNoteUnit
    {
        return new ReviewNoteUnit($this->level, $this->main_criterion_id, $this->sub_criterion_id, $this->guiding_point_id);
    }

    public function isAuthoredBy(int $userId): bool
    {
        return $this->author_user_id === $userId;
    }
}
