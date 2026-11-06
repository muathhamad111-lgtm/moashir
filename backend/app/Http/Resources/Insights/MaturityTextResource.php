<?php

declare(strict_types=1);

namespace App\Http\Resources\Insights;

use App\Models\Insights\MaturityInterpretation;
use App\Support\Insights\MaturityTextPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read MaturityInterpretation $resource
 */
final class MaturityTextResource extends JsonResource
{
    /**
     * @param  list<string>  $publishedCodes
     */
    public function __construct(MaturityInterpretation $resource, private readonly array $publishedCodes = [])
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return MaturityTextPresenter::present($this->resource, $this->publishedCodes);
    }
}
