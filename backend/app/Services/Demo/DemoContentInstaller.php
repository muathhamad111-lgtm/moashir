<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Data\Demo\DemoContent;
use App\Models\Identity\User;

final readonly class DemoContentInstaller
{
    public function __construct(
        private DemoContentSource $source,
        private DemoPagesInstaller $pages,
        private DemoKpisInstaller $kpis,
        private DemoTermsInstaller $terms,
        private DemoLegalPageInstaller $legalPage,
    ) {}

    public function install(User $editor): DemoContent
    {
        $content = $this->source->read();

        return new DemoContent(
            $this->pages->install($editor, $content),
            $this->kpis->install($editor, $content),
            $this->terms->install($editor)->published,
            $this->legalPage->install($editor),
        );
    }
}
