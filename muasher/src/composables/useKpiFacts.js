import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatRiyadhDate } from '@/utils/datetime'
import { formatKpiValue, kpiNumeric } from '@/utils/kpiNumber'

export function useKpiFacts(kpi, localized) {
  const { t, n, locale } = useI18n()

  function amount(value) {
    const unit = localized(kpi.value?.unit)
    return [formatKpiValue(n, value), unit].filter(Boolean).join(' ')
  }

  function date(value) {
    return formatRiyadhDate(value, locale.value)
  }

  return computed(() => {
    const current = kpi.value
    if (!current) return []
    const candidates = [
      ['target', kpiNumeric(current.target) !== null ? amount(current.target) : ''],
      ['baseline', kpiNumeric(current.baseline) !== null ? amount(current.baseline) : ''],
      ['category', localized(current.category?.name)],
      ['type', localized(current.type?.name)],
      ['measurementYear', current.measurementYear ? String(current.measurementYear) : ''],
      ['lastUpdate', current.lastUpdate ? date(current.lastUpdate) : ''],
      ['createdAt', current.createdAt ? date(current.createdAt) : ''],
      ['owner', localized(current.owner)],
      ['dataSource', localized(current.dataSource)],
    ]
    return candidates
      .filter(([, value]) => value)
      .map(([key, value]) => ({ key, label: t(`kpis.detail.${key}`), value }))
  })
}
