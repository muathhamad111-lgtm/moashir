import { KPI_YEAR_RANGE } from '@/constants/kpis'
import { contentReject } from '../content/contentRejection'
import { createFieldErrors } from '../content/contentValidation'
import { todayKey } from './kpiState'
import { isKpiNumber, normalizeKpiNumber } from './kpiInput'
import { historyOf } from './kpiSerializers'
import { requireKpi } from './kpiWrites'

const DATE = /^\d{4}-\d{2}-\d{2}$/

function validate(body) {
  const errors = createFieldErrors()
  errors.check(isKpiNumber(body.value), 'value', 'kpiNumber')
  const day = String(body.measuredOn ?? '')
  errors.check(DATE.test(day) && day >= `${KPI_YEAR_RANGE.min}-01-01`, 'measuredOn', 'required')
  if (DATE.test(day)) errors.check(day <= todayKey(), 'measuredOn', 'futureDate')
  errors.throwIfAny()
}

export function listKpiValues(kpiId) {
  return historyOf(requireKpi(kpiId))
}

export function recordKpiValue(kpiId, body) {
  const kpi = requireKpi(kpiId)
  validate(body)
  if (kpi.history.some((entry) => entry.measuredOn === body.measuredOn))
    throw contentReject.measurementExists()
  const latest = kpi.history.reduce(
    (max, entry) => (entry.measuredOn > max ? entry.measuredOn : max),
    '',
  )
  const value = normalizeKpiNumber(body.value)
  kpi.history.push({ value, measuredOn: body.measuredOn })
  if (body.measuredOn > latest) {
    kpi.value = value
    kpi.lastUpdate = new Date().toISOString()
  }
  return historyOf(kpi)
}
