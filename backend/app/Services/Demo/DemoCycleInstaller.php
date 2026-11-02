<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Actions\Award\CreateAwardCycle;
use App\Data\Award\CycleAttributes;
use App\Exceptions\Console\DemoDataRefused;
use App\Models\Award\AwardCycle;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Region;
use App\Models\Identity\User;
use App\Support\ValueObjects\LocalizedText;
use Carbon\CarbonImmutable;

final readonly class DemoCycleInstaller
{
    public function __construct(private CreateAwardCycle $createCycle) {}

    public function install(User $awardSecretary): AwardCycle
    {
        $version = CriteriaVersion::query()->published()->first()
            ?? throw DemoDataRefused::missingPrerequisite('no criteria version is published (moashir:criteria:install-seed)');
        $start = CarbonImmutable::now()->addDay()->startOfHour();

        return $this->createCycle->handle($awardSecretary, new CycleAttributes(
            name: new LocalizedText('الدورة التجريبية لجائزة التميز المؤسسي', 'Institutional Excellence Award - demonstration cycle'),
            dates: [
                'submission_start' => $start,
                'submission_end' => $start->addDays(45),
                'committee_start' => $start->addDays(60),
                'committee_end' => $start->addDays(90),
                'announcement_at' => $start->addDays(105),
            ],
            regionIds: array_values(Region::query()->active()->orderBy('id')->get(['id'])->map(static fn (Region $region): int => $region->id)->all()),
            criteriaVersionId: $version->id,
        ), null);
    }
}
