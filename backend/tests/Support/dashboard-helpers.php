<?php

declare(strict_types=1);

use App\Models\Identity\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

const DASHBOARD = '/api/v1/dashboard/summary';

function boardOf(User $user, ?string $board = null): TestResponse
{
    return switchTo($user)->getJson(DASHBOARD.($board === null ? '' : '?board='.$board))->assertOk();
}

/**
 * @return Collection<string, mixed>
 */
function boardCards(TestResponse $response): Collection
{
    return collect($response->json('data.cards'))->mapWithKeys(static fn (array $card): array => [$card['key'] => $card['value']]);
}

function boardWidget(TestResponse $response, string $key): mixed
{
    return collect($response->json('data.widgets'))->firstWhere('key', $key)['data'] ?? null;
}

/**
 * @return Collection<string, array<string, mixed>>
 */
function actionItems(TestResponse $response): Collection
{
    return collect($response->json('data.action_center'))->keyBy('key');
}

function dashboardQueryCount(User $user, string $board): int
{
    boardOf($user, $board);
    DB::flushQueryLog();
    DB::enableQueryLog();
    boardOf($user, $board);
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}
