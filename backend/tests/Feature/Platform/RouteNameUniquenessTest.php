<?php

declare(strict_types=1);

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;

// DEPLOY #52 regression: two routes shared the name `award.cycles.open`, so `route:cache` (run on every deploy) refused
// to serialize the routes and the release stopped before activation. Every named route must be unique.
it('gives every named route a unique name, so the route cache can be built', function (): void {
    $names = collect(Router::getRoutes()->getRoutes())
        ->map(static fn (Route $route): ?string => $route->getName())
        ->filter()
        ->countBy()
        ->filter(static fn (int $count): bool => $count > 1)
        ->keys()
        ->all();

    expect($names)->toBe([]);
});
