import { assessmentReject, assessmentRoute } from '../stores/assessments/assessmentRejection'
import { runAccreditedSchedule } from '../stores/accredited/scheduler'
import { findCycle } from '../stores/award/cycleStore'

const NUMERIC_ID = /^\d+$/

export function numericParams(params) {
  return Object.values(params).every((value) => NUMERIC_ID.test(String(value)))
}

export function cycleRoute(resolve) {
  const route = assessmentRoute(resolve)
  return (info) => (numericParams(info.params) ? route(info) : undefined)
}

export function boundCycle(params) {
  runAccreditedSchedule()
  const cycle = findCycle(params.cycle)
  if (!cycle) throw assessmentReject.notFound()
  return cycle
}

export function queryOf(request) {
  return new URL(request.url).searchParams
}
