<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;

final class ApiResponse
{
    private const int ENCODING = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function resource(JsonResource $resource, int $status = Response::HTTP_OK, array $meta = []): JsonResponse
    {
        return self::data($resource->resolve(request()), $status, $meta);
    }

    /**
     * @param  array<array-key, mixed>|null  $data
     * @param  array<string, mixed>  $meta
     */
    public static function data(?array $data, int $status = Response::HTTP_OK, array $meta = []): JsonResponse
    {
        return new JsonResponse([
            'data' => $data,
            'meta' => (object) $meta,
            'errors' => null,
        ], $status, [], self::ENCODING);
    }

    /**
     * @template TModel
     *
     * @param  LengthAwarePaginator<int, TModel>  $paginator
     * @param  class-string<JsonResource>  $resourceClass
     * @param  array<string, mixed>  $meta
     */
    public static function paginated(LengthAwarePaginator $paginator, string $resourceClass, array $meta = []): JsonResponse
    {
        $items = $resourceClass::collection($paginator->getCollection())->resolve(request());

        return self::data($items, Response::HTTP_OK, [
            ...$meta,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * @template TModel
     *
     * @param  LengthAwarePaginator<int, TModel>  $paginator
     * @param  list<array<array-key, mixed>>  $items
     * @param  array<string, mixed>  $meta
     */
    public static function page(LengthAwarePaginator $paginator, array $items, array $meta = []): JsonResponse
    {
        return self::data($items, Response::HTTP_OK, [
            ...$meta,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public static function noContent(): Response
    {
        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public static function error(ApiError $error): JsonResponse
    {
        return new JsonResponse([
            'data' => null,
            'meta' => (object) $error->meta,
            'message' => $error->message(),
            'code' => $error->code,
            'errors' => (object) $error->fields,
        ], $error->status, $error->headers, self::ENCODING);
    }
}
