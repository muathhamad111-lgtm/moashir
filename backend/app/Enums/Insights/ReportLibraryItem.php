<?php

declare(strict_types=1);

namespace App\Enums\Insights;

enum ReportLibraryItem: string
{
    case FeedbackReport = 'feedback_report';
    case BoardReport = 'board_report';
    case Certificate = 'certificate';
}
