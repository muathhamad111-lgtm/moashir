<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Award\SecretaryRequestDetail;
use App\Models\Assessments\Assessment;

final readonly class ShowDecidedSecretaryRequest
{
    public function __construct(private ShowSecretaryRequest $show) {}

    public function handle(Assessment $decided): SecretaryRequestDetail
    {
        return $this->show->handle(Assessment::query()->findOrFail($decided->id));
    }
}
