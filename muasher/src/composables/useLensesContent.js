import { computed } from 'vue'
import { CMS_PAGE_KEYS } from '@/constants/cms'
import { usePublicPage } from './usePublicPage'

export const LENS_FALLBACK = Object.freeze([
  { key: 'grantor', icon: 'sar' },
  { key: 'supervisory', icon: 'building' },
  { key: 'community', icon: 'users-two' },
  { key: 'vision', icon: 'flag' },
  { key: 'strategic', icon: 'chess' },
])

export function useLensesContent() {
  const page = usePublicPage(CMS_PAGE_KEYS.LENSES)
  const { localized, sectionOfType } = page
  const overview = computed(() => sectionOfType('rich_text'))
  const cardsSection = computed(
    () => page.sections.value.find((section) => section.items?.length) ?? null,
  )

  return {
    state: page.state,
    load: page.load,
    localized,
    title: computed(() => localized(overview.value?.title) || localized(page.page.value?.title)),
    lead: computed(() => localized(overview.value?.subtitle)),
    rule: computed(() => localized(overview.value?.description)),
    lenses: computed(() => cardsSection.value?.items ?? []),
  }
}
