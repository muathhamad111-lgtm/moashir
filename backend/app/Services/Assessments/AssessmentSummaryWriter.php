<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\AssessmentSummaryChange;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentSummary;
use App\Models\Identity\User;
use App\Support\RequestContext;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\Date;

final readonly class AssessmentSummaryWriter
{
    public function __construct(private AssessmentSummaryReader $reader, private RequestContext $context) {}

    public function write(Assessment $locked, string $body, User $author): ?AssessmentSummaryChange
    {
        $body = trim($body);
        $current = $this->reader->currentOf($locked);

        if (($current->body ?? '') === $body) {
            return null;
        }

        $current?->forceFill(['superseded_at' => Date::now()])->save();

        if ($body === '') {
            return new AssessmentSummaryChange(ScalarValue::int($current?->version), true);
        }

        $created = AssessmentSummary::query()->create([
            'assessment_id' => $locked->id,
            'body' => $body,
            'author_user_id' => $author->id,
            'version' => $this->nextVersion($locked),
            'change_reason' => $this->context->reason(),
        ]);

        return new AssessmentSummaryChange($created->version, false);
    }

    private function nextVersion(Assessment $locked): int
    {
        return ScalarValue::int(AssessmentSummary::query()->where('assessment_id', $locked->id)->max('version')) + 1;
    }
}
