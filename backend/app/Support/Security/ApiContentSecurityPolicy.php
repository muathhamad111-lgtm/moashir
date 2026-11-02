<?php

declare(strict_types=1);

namespace App\Support\Security;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;

final class ApiContentSecurityPolicy implements Preset
{
    public function configure(Policy $policy): void
    {
        $policy
            ->add(Directive::DEFAULT, Keyword::NONE)
            ->add(Directive::BASE, Keyword::NONE)
            ->add(Directive::FORM_ACTION, Keyword::NONE)
            ->add(Directive::FRAME_ANCESTORS, Keyword::NONE);
    }
}
