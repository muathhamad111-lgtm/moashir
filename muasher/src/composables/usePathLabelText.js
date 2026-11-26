import { computed, toValue } from 'vue'
import { useI18n } from 'vue-i18n'
import { localizedText } from '@/utils/localizedText'
import { usePathNames } from '@/composables/usePathNames'

export function usePathLabelText(path, label) {
  const { te, locale } = useI18n()
  const { pathName } = usePathNames()

  return computed(() => {
    const served = localizedText(toValue(label), locale.value)
    if (served) return served
    const key = toValue(path)
    return te(`paths.labels.${key}`) ? pathName(key) : key
  })
}
