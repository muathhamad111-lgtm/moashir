<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Contracts\Finance\LinkedRequestReader;
use App\Data\Finance\LinkedRequest;
use App\Data\Finance\OpenedRequest;
use App\Models\Assessments\Assessment;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Support\Assessments\StatusMask;

final readonly class LinkedRequestByPayment implements LinkedRequestReader
{
    public function __construct(private SubmissionProgressReader $progress) {}

    public function forPayment(Payment $payment, User $viewer): ?LinkedRequest
    {
        $assessment = Assessment::query()->where('payment_id', $payment->id)->first();

        if (! $assessment instanceof Assessment) {
            return null;
        }

        return new LinkedRequest(
            new OpenedRequest($assessment->id, $assessment->request_number, $assessment->path),
            StatusMask::statusFor($assessment, $viewer),
            $viewer->can('viewRequest', $assessment),
            $this->progress->read($assessment),
        );
    }
}
