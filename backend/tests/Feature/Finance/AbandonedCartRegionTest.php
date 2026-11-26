<?php

declare(strict_types=1);

use App\Models\Finance\CheckoutIntent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

it('lists each abandoned cart with the region of its entity, loaded once for the page', function (): void {
    ['owner' => $owner, 'entity' => $entity] = payingEntity();
    openedPayment($owner);
    openedPayment(ownerOf(entityWithOwner()));
    CheckoutIntent::query()->update(['last_seen_at' => now()->subHours(25)]);

    switchTo(systemAdmin());
    DB::enableQueryLog();
    $rows = $this->getJson('/api/v1/messaging/abandoned-carts')->assertOk()->json('data');
    $regionQueries = collect(DB::getQueryLog())->filter(static fn (array $query): bool => str_contains($query['query'], 'from "regions"'));

    $row = collect($rows)->firstWhere('entity.id', $entity->id);
    expect($rows)->toHaveCount(2)
        ->and($row['region'])->toBe(['id' => $entity->region->id, 'code' => $entity->region->code, 'name' => $entity->region->name->toArray()])
        ->and($regionQueries)->toHaveCount(1);
});
