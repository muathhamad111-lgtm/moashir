import { useI18n } from 'vue-i18n'
import { formatWeight } from '@/utils/weight'

export function useCriteria() {
  const { locale } = useI18n()

  function localized(content) {
    if (content === null || content === undefined) return ''
    if (typeof content === 'string') return content
    return content[locale.value] || content.ar || content.en || ''
  }

  function weight(value) {
    return formatWeight(value, locale.value)
  }

  return { locale, localized, weight }
}
