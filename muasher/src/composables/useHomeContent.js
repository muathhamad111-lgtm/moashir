import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { usePublicHomeStore } from '@/stores/publicHome'
import { localizedText } from '@/utils/localizedText'

export function usePublicHome({ auto = true } = {}) {
  const { locale } = useI18n()
  const store = usePublicHomeStore()
  const { sections, siteSettings, seo, status } = storeToRefs(store)

  const orderedSections = computed(() => sections.value)

  function localized(value) {
    return localizedText(value, locale.value)
  }

  function sectionByKey(key) {
    return sections.value.find((section) => section.key === key) ?? null
  }

  function sectionOfType(type) {
    return sections.value.find((section) => section.type === type) ?? null
  }

  if (auto) onMounted(() => store.ensureLoaded())

  return {
    sections,
    orderedSections,
    siteSettings,
    seo,
    status,
    load: () => store.load(),
    localized,
    sectionByKey,
    sectionOfType,
  }
}
