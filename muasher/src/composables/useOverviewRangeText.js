import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { COMPARISON_BASES } from '@/constants/dashboardOverview'
import { longDayLabel } from '@/utils/charts/bucketLabels'

/**
 * The overview's period line: the chosen range, and the range it is compared with, named by how
 * the API chose it.
 */
export function useOverviewRangeText(overview) {
  const { t, locale } = useI18n()

  function rangeText(window) {
    return window?.from
      ? t('dashboard.overview.range', {
          from: longDayLabel(window.from, locale.value),
          to: longDayLabel(window.to, locale.value),
        })
      : ''
  }

  const periodText = computed(() => rangeText(overview.value?.period))
  const previousText = computed(() => {
    const previous = overview.value?.previousPeriod
    if (!previous) return ''
    const basis = Object.values(COMPARISON_BASES).includes(previous.basis)
      ? previous.basis
      : COMPARISON_BASES.PRECEDING
    return t(`dashboard.overview.compare.${basis}`, { range: rangeText(previous) })
  })

  return { periodText, previousText }
}
