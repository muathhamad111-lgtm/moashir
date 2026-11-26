<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Actions\Reports\GetFeedbackReport;
use App\Actions\Reports\IssueFeedbackReportExport;
use App\Actions\Reports\RenderFeedbackReportPdf;
use App\Data\Documents\RenderedDocument;
use App\Http\Requests\Reports\DownloadFeedbackReportRequest;
use App\Http\Requests\Reports\ExportFeedbackReportRequest;
use App\Http\Resources\Files\SignedDownloadResource;
use App\Http\Resources\Reports\FeedbackReportResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class FeedbackReportController
{
    public function show(Assessment $assessment, GetFeedbackReport $report): JsonResponse
    {
        return ApiResponse::resource(new FeedbackReportResource($report->handle($assessment)));
    }

    public function export(ExportFeedbackReportRequest $request, Assessment $assessment, #[CurrentUser] User $actor, IssueFeedbackReportExport $export): JsonResponse
    {
        return ApiResponse::resource(new SignedDownloadResource($export->handle($actor, $assessment, $request->documentLocale())));
    }

    public function pdf(DownloadFeedbackReportRequest $request, Assessment $assessment, #[CurrentUser] User $actor, RenderFeedbackReportPdf $render): Response
    {
        $document = $render->handle($actor, $assessment, $request->documentLocale(), $request->boundUserId());

        return new Response($document->contents, Response::HTTP_OK, [
            'Content-Type' => RenderedDocument::PDF_MIME_TYPE,
            'Content-Length' => (string) strlen($document->contents),
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $document->fileName, 'feedback-report.pdf'),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
