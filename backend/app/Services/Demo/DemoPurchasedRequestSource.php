<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Actions\Assessments\OpenPaidAccreditedRequest;
use App\Contracts\Demo\DemoAccreditedRequestSource;
use App\Data\Demo\DemoAccreditedResult;
use App\Exceptions\Console\DemoDataRefused;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;

final readonly class DemoPurchasedRequestSource implements DemoAccreditedRequestSource
{
    public function __construct(
        private DemoPaymentInstaller $payments,
        private OpenPaidAccreditedRequest $open,
        private DemoRequestCompletion $completion,
    ) {}

    public function completedRequestFor(DemoAccreditedResult $plan, User $assessor): Assessment
    {
        $payment = $this->payments->installPaid($plan->owner);
        $request = $this->open->handle($payment->id) ?? throw DemoDataRefused::missingPrerequisite('the demo accredited request could not be opened');
        $this->completion->complete($request, $plan, $assessor);

        return $request;
    }
}
