import { evidenceOfMain, evidenceOfSub } from './evidenceRules'
import { ENTITY_EDITABLE_STATUSES } from '@/constants/accredited'

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

function isDraft(form) {
  return ENTITY_EDITABLE_STATUSES.includes(form?.assessment?.status)
}

// While the entity still works on the form, a sub with nothing missing (all its evidence optional)
// counts as complete only once the entity added something to it or moved past its station.
function isMainSubComplete(form, main, sub, gaps) {
  if (gaps.has(sub.id)) return false
  if (!isDraft(form)) return true
  return isStarted(form, sub) || (form.passedStations ?? []).includes(main.id)
}

function mainGapIds(missing) {
  return new Set((missing?.missingMainAttachments ?? []).map((item) => item.mainCriterionId))
}

// A main criterion's own required attachments count as one more unit of its station.
function mainUnitOf(form, main, mainGaps) {
  const counted = (main.attachments ?? []).some((slot) => slot.required)
  return {
    total: counted ? 1 : 0,
    complete: counted && !mainGaps.has(main.id) ? 1 : 0,
    started: evidenceOfMain(form.evidence ?? [], main.id).length > 0,
  }
}

export function stationStateOf(complete, total, started) {
  if (total > 0 && complete === total) return STATION_STATES.COMPLETE
  return complete > 0 || started ? STATION_STATES.PARTIAL : STATION_STATES.EMPTY
}

// total / complete count the subs (the station badge); units add the main criterion's own
// required attachments, so a station is complete and the form ready only with them.
export function stationsOf(form) {
  const gaps = gapSubIds(form?.missing)
  const mainGaps = mainGapIds(form?.missing)
  return (form?.criteria?.mains ?? []).map((main) => {
    const complete = main.subs.filter((sub) => isMainSubComplete(form, main, sub, gaps)).length
    const mainUnit = mainUnitOf(form, main, mainGaps)
    const started = mainUnit.started || main.subs.some((sub) => isStarted(form, sub))
    const units = {
      total: main.subs.length + mainUnit.total,
      complete: complete + mainUnit.complete,
    }
    return {
      id: main.id,
      name: main.name,
      total: main.subs.length,
      complete,
      units,
      state: stationStateOf(units.complete, units.total, started),
    }
  })
}

export function submissionProgressOf(form) {
  const stations = stationsOf(form)
  const total = stations.reduce((sum, station) => sum + station.units.total, 0)
  const complete = stations.reduce((sum, station) => sum + station.units.complete, 0)
  return { total, complete, remaining: total - complete }
}

// True once the entity saved any CV text or uploaded any file, to a sub or to a main criterion.
export function hasEntityInput(form) {
  const hasCv = Object.values(form?.cv ?? {}).some((text) => Boolean(String(text ?? '').trim()))
  return hasCv || (form?.evidence ?? []).length > 0
}

export function isSubComplete(form, subId) {
  const main = (form?.criteria?.mains ?? []).find((item) =>
    item.subs.some((sub) => sub.id === subId),
  )
  const sub = main?.subs.find((item) => item.id === subId)
  return Boolean(sub) && isMainSubComplete(form, main, sub, gapSubIds(form?.missing))
}
