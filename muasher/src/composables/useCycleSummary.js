import { computed, toValue } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatRiyadhDate } from '@/utils/datetime'
import { localizedText } from '@/utils/localizedText'
import { summarySections } from '@/utils/award/cycleSummary'

export function useCycleSummary(cycle, regionsTotal) {
  const { t, locale } = useI18n()

  function formatter() {
    const empty = t('cycles.setup.review.empty')
    const plain = (value) =>
      value === null || value === undefined || value === '' ? empty : String(value)
    const date = (value) => formatRiyadhDate(value, locale.value) || empty
    return {
      t,
      plain,
      date,
      regionsTotal: toValue(regionsTotal),
      text: (value) => localizedText(value, locale.value) || empty,
      window: (start, end) =>
        t('cycles.setup.review.window', { start: date(start), end: date(end) }),
      flag: (value) => t(value ? 'cycles.setup.review.on' : 'cycles.setup.review.off'),
      list: (names) => names.filter(Boolean).join(t('common.listSeparator')) || empty,
    }
  }

  return computed(() => summarySections(toValue(cycle), formatter()))
}
