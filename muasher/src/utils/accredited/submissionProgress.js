import { evidenceOfSub } from './evidenceRules'

export const STATION_STATES = Object.freeze({
  COMPLETE: 'complete',
  PARTIAL: 'partial',
  EMPTY: 'empty',
})

function gapSubIds(missing) {
  return new Set([
    ...(missing?.missingCv ?? []).map((item) => item.subCriterionId),
    ...(missing?.missingAttachments ?? []).map((item) => item.subCriterionId),
  ])
}

function isStarted(form, sub) {
  return (
    evidenceOfSub(form.evidence ?? [], sub.id).length > 0 ||
    Boolean(String(form.cv?.[String(sub.id)] ?? '').trim())
  )
}

function stateOf(complete, total, started) {
  if (total > 0 && complete === total) return STATION_STATES.COMPLETE
  return complete > 0 || started ? STATION_STATES.PARTIAL : STATION_STATES.EMPTY
}

export function stationsOf(form) {
  const gaps = gapSubIds(form?.missing)
  return (form?.criteria?.mains ?? []).map((main) => {
    const complete = main.subs.filter((sub) => !gaps.has(sub.id)).length
    const started = main.subs.some((sub) => isStarted(form, sub))
    return {
      id: main.id,
      code: main.code,
      name: main.name,
      total: main.subs.length,
      complete,
      state: stateOf(complete, main.subs.length, started),
    }
  })
}

export function submissionProgressOf(form) {
  const stations = stationsOf(form)
  const total = stations.reduce((sum, station) => sum + station.total, 0)
  const complete = stations.reduce((sum, station) => sum + station.complete, 0)
  return { total, complete, remaining: total - complete }
}

export function isSubComplete(form, subId) {
  return !gapSubIds(form?.missing).has(subId)
}
