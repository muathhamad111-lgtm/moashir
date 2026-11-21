import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'

const COMPACT_FROM = 10000

// Value formatters for chart axes, tooltips and data tables (text only, no markup).
export function useOverviewFormats() {
  const { t, n } = useI18n()

  function count(value) {
    if (value === null || value === undefined) return t('dashboard.values.none')
    return formatNumber(n, value)
  }

  function money(value) {
    if (value === null || value === undefined) return t('dashboard.values.none')
    const number = Number(value)
    const amount =
      number >= COMPACT_FROM
        ? new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(
            number,
          )
        : formatNumber(n, number)
    return t('dashboard.overview.money', { amount })
  }

  // Axis ticks of an amount chart: compact numbers; the chart title names the currency.
  function amountTick(value) {
    const number = Number(value) || 0
    return number >= COMPACT_FROM
      ? new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(
          number,
        )
      : formatNumber(n, number)
  }

  function share(value) {
    return t('public.home.percent', { value: formatNumber(n, Number(value).toFixed(1)) })
  }

  function percent(value) {
    if (value === null || value === undefined || value === '') return t('dashboard.values.none')
    return t('public.home.percent', { value: formatNumber(n, value) })
  }

  return { count, money, amountTick, share, percent }
}
