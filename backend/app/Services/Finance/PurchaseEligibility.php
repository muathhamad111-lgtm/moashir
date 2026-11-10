<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Contracts\Finance\PurchaseEligibilityCheck;
use App\Data\Finance\OrderTerms;
use App\Models\Entities\Entity;
use Illuminate\Container\Attributes\Tag;

final readonly class PurchaseEligibility
{
    public const string TAG = 'finance.purchase-eligibility-checks';

    /**
     * @var list<PurchaseEligibilityCheck>
     */
    private array $checks;

    /**
     * @param  iterable<PurchaseEligibilityCheck>  $checks
     */
    public function __construct(#[Tag(self::TAG)] iterable $checks)
    {
        $this->checks = iterator_to_array($checks, false);
    }

    public function assertMayPurchase(Entity $entity, OrderTerms $terms): void
    {
        foreach ($this->checks as $check) {
            $check->assertMayPurchase($entity, $terms);
        }
    }
}
