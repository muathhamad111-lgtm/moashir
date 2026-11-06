<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Dashboards\ActionItems\AssessorActionItems;
use App\Services\Dashboards\ActionItems\BankTransferReviewActionItems;
use App\Services\Dashboards\ActionItems\CommitteeActionItems;
use App\Services\Dashboards\ActionItems\ContactActionItems;
use App\Services\Dashboards\ActionItems\ContentActionItems;
use App\Services\Dashboards\ActionItems\CriteriaActionItems;
use App\Services\Dashboards\ActionItems\EntityActionItems;
use App\Services\Dashboards\ActionItems\EntityAdministrationActionItems;
use App\Services\Dashboards\ActionItems\EntityBankTransferActionItems;
use App\Services\Dashboards\ActionItems\FilteringActionItems;
use App\Services\Dashboards\ActionItems\FinanceActionItems;
use App\Services\Dashboards\ActionItems\RequestQueueActionItems;
use App\Services\Dashboards\ActionItems\SecretaryActionItems;
use App\Services\Dashboards\ActionItems\SenderContactActionItems;
use App\Services\Dashboards\ActionItemSources;
use App\Services\Dashboards\Boards\AssessorBoardQuery;
use App\Services\Dashboards\Boards\AwardSecretaryBoardQuery;
use App\Services\Dashboards\Boards\CommitteeAssessorBoardQuery;
use App\Services\Dashboards\Boards\ContentOfficerBoardQuery;
use App\Services\Dashboards\Boards\CriteriaOfficerBoardQuery;
use App\Services\Dashboards\Boards\EntityBoardQuery;
use App\Services\Dashboards\Boards\ExecutiveBoardQuery;
use App\Services\Dashboards\Boards\FilteringOfficerBoardQuery;
use App\Services\Dashboards\Boards\FinanceOfficerBoardQuery;
use App\Services\Dashboards\Boards\RequestFollowerBoardQuery;
use App\Services\Dashboards\Boards\SystemAdminBoardQuery;
use App\Services\Dashboards\DashboardBoardQueries;
use Illuminate\Support\ServiceProvider;

final class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([
            SystemAdminBoardQuery::class,
            ExecutiveBoardQuery::class,
            RequestFollowerBoardQuery::class,
            AssessorBoardQuery::class,
            CommitteeAssessorBoardQuery::class,
            FilteringOfficerBoardQuery::class,
            AwardSecretaryBoardQuery::class,
            EntityBoardQuery::class,
            FinanceOfficerBoardQuery::class,
            ContentOfficerBoardQuery::class,
            CriteriaOfficerBoardQuery::class,
        ], DashboardBoardQueries::TAG);

        $this->app->tag([
            EntityActionItems::class,
            RequestQueueActionItems::class,
            AssessorActionItems::class,
            CommitteeActionItems::class,
            FilteringActionItems::class,
            SecretaryActionItems::class,
            FinanceActionItems::class,
            BankTransferReviewActionItems::class,
            EntityBankTransferActionItems::class,
            EntityAdministrationActionItems::class,
            CriteriaActionItems::class,
            ContentActionItems::class,
            ContactActionItems::class,
            SenderContactActionItems::class,
        ], ActionItemSources::TAG);
    }
}
