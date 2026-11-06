<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Insights;

use App\Actions\Insights\IssueBoardReportExport;
use App\Actions\Insights\RenderBoardReportPdf;
use App\Data\Documents\RenderedDocument;
use App\Http\Requests\Insights\DownloadBoardReportRequest;
use App\Http\Requests\Insights\ExportBoardReportRequest;
use App\Http\Resources\Files\SignedDownloadResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class BoardReportController
{
    public function export(ExportBoardReportRequest $request, Assessment $assessment, #[CurrentUser] User $actor, IssueBoardReportExport $export): JsonResponse
    {
        return ApiResponse::resource(new SignedDownloadResource($export->handle($actor, $assessment, $request->documentLocale())));
    }

    public function pdf(DownloadBoardReportRequest $request, Assessment $assessment, #[CurrentUser] User $actor, RenderBoardReportPdf $render): Response
    {
        $document = $render->handle($actor, $assessment, $request->documentLocale(), $request->boundUserId());

        return new Response($document->contents, Response::HTTP_OK, [
            'Content-Type' => RenderedDocument::PDF_MIME_TYPE,
            'Content-Length' => (string) strlen($document->contents),
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $document->fileName, 'board-report.pdf'),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
