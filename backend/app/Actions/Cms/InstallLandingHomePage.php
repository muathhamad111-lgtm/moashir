<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Data\Cms\LandingContent;
use App\Data\Cms\LandingInstallation;
use App\Data\Cms\LandingInstallOptions;
use App\Enums\Cms\CmsPageKey;
use App\Models\Cms\CmsPage;
use App\Services\Cms\Landing\HomeSectionsReplacer;
use App\Services\Cms\Landing\LandingContentSource;
use App\Services\Cms\Landing\LandingEditorResolver;
use App\Services\Cms\Landing\LandingLinksReplacer;
use Illuminate\Support\Facades\DB;

final readonly class InstallLandingHomePage
{
    public function __construct(
        private LandingContentSource $source,
        private LandingEditorResolver $editors,
        private HomeSectionsReplacer $sections,
        private LandingLinksReplacer $links,
        private PublishCmsPage $publish,
    ) {}

    public function handle(LandingInstallOptions $options): LandingInstallation
    {
        $content = $this->source->read();
        $home = CmsPage::query()->where('key', CmsPageKey::Home)->firstOrFail();

        if (! $options->replacesCurrentLanding && $this->holdsLanding($home, $content)) {
            return LandingInstallation::unchanged();
        }

        $editor = $this->editors->resolve($options->editorEmail);

        return DB::transaction(function () use ($editor, $home, $content): LandingInstallation {
            $sections = $this->sections->replace($editor, $home, $content);
            $replacedLinks = $this->links->replace($editor, $content);
            $this->publish->handle($editor, $home);

            return LandingInstallation::installed($sections, $this->publishedSectionsOf($home), $replacedLinks);
        });
    }

    private function holdsLanding(CmsPage $home, LandingContent $content): bool
    {
        $current = $home->sections()->pluck('key')->all();
        $landing = $content->sectionKeys();
        sort($current);
        sort($landing);

        return $current === $landing;
    }

    private function publishedSectionsOf(CmsPage $home): int
    {
        $sections = $home->refresh()->published_content['sections'] ?? [];

        return is_array($sections) ? count($sections) : 0;
    }
}
