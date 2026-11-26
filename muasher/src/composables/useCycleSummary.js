import { computed, toValue } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatRiyadhDate } from '@/utils/datetime'
import { localizedText } from '@/utils/localizedText'
import { summarySections } from '@/utils/award/cycleSummary'
import { overviewFacts, detailGroups } from '@/utils/award/cycleOverview'

function useCycleFormat(regionsTotal) {
  const { t, locale } = useI18n()

  return function formatter() {
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
}

export function useCycleSummary(cycle, regionsTotal, committee) {
  const formatter = useCycleFormat(regionsTotal)
  return computed(() => summarySections(toValue(cycle), formatter(), toValue(committee)))
}

// The view page: the key facts on top, then the remaining sections grouped into compact cards.
export function useCycleOverview(cycle, regionsTotal, committee) {
  const formatter = useCycleFormat(regionsTotal)
  const facts = computed(() => overviewFacts(toValue(cycle), formatter()))
  const groups = computed(() =>
    detailGroups(summarySections(toValue(cycle), formatter(), toValue(committee)), formatter()),
  )
  return { facts, groups }
}
