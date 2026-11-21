<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Insights;

use App\Actions\Insights\DeleteMaturityText;
use App\Actions\Insights\ListMaturityTexts;
use App\Actions\Insights\SaveMaturityText;
use App\Enums\Insights\MaturityBand;
use App\Http\Requests\Insights\IndexMaturityTextsRequest;
use App\Http\Requests\Insights\SaveMaturityTextRequest;
use App\Http\Resources\Insights\MaturityTextResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Models\Insights\MaturityInterpretation;
use App\Services\Insights\PublishedMainCriterionCodes;
use App\Support\Insights\MaturityTextPresenter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class MaturityTextController
{
    public function index(IndexMaturityTextsRequest $request, ListMaturityTexts $list): JsonResponse
    {
        $listing = $list->handle($request->perPage());

        return ApiResponse::page($listing->rows, array_values(array_map(
            static fn (MaturityInterpretation $row): array => MaturityTextPresenter::present($row, $listing->publishedCodes),
            $listing->rows->items(),
        )));
    }

    public function update(SaveMaturityTextRequest $request, MaturityBand $band, #[CurrentUser] User $actor, SaveMaturityText $save, PublishedMainCriterionCodes $codes): JsonResponse
    {
        return ApiResponse::resource(new MaturityTextResource($save->handle($actor, $request->edit($band)), $codes->all()));
    }

    public function destroy(MaturityInterpretation $maturityText, DeleteMaturityText $delete): Response
    {
        $delete->handle($maturityText);

        return ApiResponse::noContent();
    }
}
