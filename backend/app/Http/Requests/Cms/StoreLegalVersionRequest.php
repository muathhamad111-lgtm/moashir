<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Data\Cms\LegalDraft;
use App\Enums\Identity\TermsKind;
use LogicException;

final class StoreLegalVersionRequest extends LegalVersionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->textRules(partial: false);
    }

    public function draft(TermsKind $kind): LegalDraft
    {
        return new LegalDraft(
            $kind,
            $this->versionLabel() ?? throw new LogicException('The version label is validated as required.'),
            $this->localizedText('title') ?? throw new LogicException('The title is validated as required.'),
            $this->localizedText('body') ?? throw new LogicException('The body is validated as required.'),
        );
    }
}
