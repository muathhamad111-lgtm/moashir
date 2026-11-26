import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { contentService } from '@/services/contentService'
import { PUBLIC_PAGE_STATES } from '@/constants/cms'
import { localizedText } from '@/utils/localizedText'
import { usePageSeo } from './usePageSeo'

const NOT_FOUND = 404

export function usePublicPage(pageKey, { auto = true } = {}) {
  const { locale } = useI18n()
  const page = ref(null)
  const state = ref(PUBLIC_PAGE_STATES.LOADING)

  const sections = computed(() => page.value?.sections ?? [])

  function localized(value) {
    return localizedText(value, locale.value)
  }

  function sectionsOfType(type) {
    return sections.value.filter((section) => section.type === type)
  }

  function sectionOfType(type, position = 0) {
    return sectionsOfType(type)[position] ?? null
  }

  async function load() {
    state.value = PUBLIC_PAGE_STATES.LOADING
    try {
      const { data } = await contentService.getPage(pageKey)
      page.value = data ?? null
      state.value = PUBLIC_PAGE_STATES.READY
    } catch (error) {
      page.value = null
      state.value =
        error?.response?.status === NOT_FOUND
          ? PUBLIC_PAGE_STATES.UNPUBLISHED
          : PUBLIC_PAGE_STATES.ERROR
    }
  }

  usePageSeo({
    title: () => localized(page.value?.seo?.title),
    description: () => localized(page.value?.seo?.description),
  })

  if (auto) onMounted(load)

  return { page, state, sections, localized, sectionOfType, sectionsOfType, load }
}
