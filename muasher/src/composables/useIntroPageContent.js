import { computed } from 'vue'
import { CMS_PAGE_KEYS } from '@/constants/cms'
import { usePublicPage } from './usePublicPage'

function copyOf(section, localized) {
  if (!section) return null
  const title = localized(section.title)
  const body = localized(section.description)
  return title || body ? { title, body } : null
}

export function useAccreditedIntroContent() {
  const page = usePublicPage(CMS_PAGE_KEYS.ACCREDITED_INTRO)
  const { localized, sectionOfType } = page
  const overview = computed(() => sectionOfType('rich_text', 0))
  const benefitsSection = computed(() => sectionOfType('faq'))

  return {
    state: page.state,
    localized,
    title: computed(() => localized(overview.value?.title)),
    subtitle: computed(() => localized(overview.value?.subtitle)),
    description: computed(() => localized(overview.value?.description)),
    whyRecommended: computed(() => copyOf(benefitsSection.value, localized)),
    benefits: computed(() => benefitsSection.value?.items ?? []),
    fees: computed(() => copyOf(sectionOfType('pricing_box'), localized)),
    payment: computed(() => copyOf(sectionOfType('rich_text', 1), localized)),
  }
}

export function useAdvancedIntroContent() {
  const page = usePublicPage(CMS_PAGE_KEYS.ADVANCED_INTRO)
  const { localized, sectionOfType } = page
  const listTypes = ['winners', 'logos']

  return {
    state: page.state,
    localized,
    highlights: computed(() =>
      [sectionOfType('rich_text'), sectionOfType('cycle_box')]
        .filter(Boolean)
        .map((section) => ({ id: section.id, ...copyOf(section, localized) })),
    ),
    groups: computed(() =>
      page.sections.value
        .filter((section) => listTypes.includes(section.type) && section.items?.length)
        .map((section) => ({
          id: section.id,
          type: section.type,
          title: localized(section.title),
          items: section.items,
        })),
    ),
  }
}

export function useBagDetailsContent() {
  const page = usePublicPage(CMS_PAGE_KEYS.BAG_DETAILS)
  const { localized, sectionOfType } = page
  const faqSection = computed(() => sectionOfType('faq'))

  return {
    state: page.state,
    overview: computed(() => copyOf(sectionOfType('rich_text'), localized)),
    faqTitle: computed(() => localized(faqSection.value?.title)),
    faqs: computed(() =>
      (faqSection.value?.items ?? []).map((entry) => ({
        id: `bag-cms-faq-${entry.id}`,
        title: localized(entry.title),
        content: localized(entry.description),
      })),
    ),
  }
}
