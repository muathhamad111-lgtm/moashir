<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Data\Criteria\CriteriaChange;
use App\Data\Criteria\TextCorrection;
use App\Data\Criteria\TextCorrectionBatch;
use App\Enums\Criteria\CriteriaChangeType;
use App\Enums\Criteria\CriteriaNodeType;
use App\Exceptions\Criteria\CriteriaTreeRejected;
use App\Models\Criteria\AttachmentRequirement;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final readonly class CriteriaTextCorrector
{
    public function __construct(private CriteriaNodeLocator $locator) {}

    /**
     * @return list<CriteriaChange>
     */
    public function apply(CriteriaVersion $version, TextCorrectionBatch $batch): array
    {
        DB::select("SELECT set_config('moashir.criteria_text_correction', 'on', true)");

        $changes = [];
        $label = $batch->label instanceof LocalizedText ? $this->correct($version, (string) $version->version_number, CriteriaNodeType::Version, ['label' => $batch->label]) : null;

        if ($label instanceof CriteriaChange) {
            $changes[] = $label;
        }

        foreach ($batch->corrections as $index => $correction) {
            $change = $this->correctNode($version, $correction, $index);

            if ($change instanceof CriteriaChange) {
                $changes[] = $change;
            }
        }

        return $changes;
    }

    private function correctNode(CriteriaVersion $version, TextCorrection $correction, int $index): ?CriteriaChange
    {
        $node = $this->locator->inVersion($version, $correction->nodeType, $correction->nodeId)
            ?? throw CriteriaTreeRejected::nodeNotInVersion($index);

        $code = $node instanceof AttachmentRequirement ? $node->key : $this->codeOf($node);

        return $this->correct($node, $code, $correction->nodeType, $correction->fields);
    }

    /**
     * @param  array<string, LocalizedText|null>  $fields
     */
    private function correct(Model $node, string $code, CriteriaNodeType $type, array $fields): ?CriteriaChange
    {
        $before = [];
        $after = [];

        foreach ($fields as $field => $text) {
            $current = $node->getAttribute($field);
            $current = $current instanceof LocalizedText ? $current : null;

            if ($this->isSame($current, $text)) {
                continue;
            }

            $before[$field] = $current?->toArray();
            $after[$field] = $text?->toArray();
            $node->setAttribute($field, $text);
        }

        if ($after === []) {
            return null;
        }

        $node->save();
        $id = $node->getKey();

        return new CriteriaChange($type, $code, CriteriaChangeType::TextCorrected, is_int($id) ? $id : null, is_int($id) ? $id : null, array_keys($after), $before, $after);
    }

    private function codeOf(MainCriterion|SubCriterion|GuidingPoint $node): string
    {
        return $node->code;
    }

    private function isSame(?LocalizedText $current, ?LocalizedText $next): bool
    {
        if (! $current instanceof LocalizedText || ! $next instanceof LocalizedText) {
            return $current === $next;
        }

        return $current->equals($next);
    }
}
