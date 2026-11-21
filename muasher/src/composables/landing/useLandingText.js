import { useI18n } from 'vue-i18n'
import { localizedText } from '@/utils/localizedText'

export function useLandingText() {
  const { locale } = useI18n()

  function text(value) {
    return localizedText(value, locale.value) ?? ''
  }

  function metaText(content, key) {
    return text(content?.meta?.[key])
  }

  function itemsWithRole(section, role) {
    return (section?.items ?? []).filter((item) => item.meta?.role === role)
  }

  function firstWithRole(section, role) {
    return itemsWithRole(section, role)[0] ?? null
  }

  function heading(section) {
    return {
      eyebrow: metaText(section, 'eyebrow'),
      title: text(section?.title),
      highlight: metaText(section, 'highlight'),
      lede: text(section?.description),
    }
  }

  return { text, metaText, itemsWithRole, firstWithRole, heading }
}
