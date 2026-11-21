<?php

declare(strict_types=1);

namespace App\Enums\Documents;

enum DocumentTemplate: string
{
    case PaymentReceipt = 'payment_receipt';
    case AccreditedCertificate = 'accredited_certificate';
    case FeedbackReport = 'feedback_report';
    case BoardReport = 'board_report';
}
