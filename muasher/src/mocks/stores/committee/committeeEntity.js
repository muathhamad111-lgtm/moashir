import { entityProfileOf, entitySummary } from '../entitySerializers'
import { entityOf } from '../assessments/assessmentsStore'
import { findRegion } from '../regionsStore'
import { assessmentSettings } from '../accredited/assessmentSettingsStore'

const CODE_PREFIX = 'C-'
const HASH_MULTIPLIER = 2654435761
const CODE_LENGTH = 8

export function placeOfRegion(regionId) {
  const region = regionId ? findRegion(regionId) : null
  return region ? { id: region.id, code: region.code, name: { ...region.name } } : null
}

export function committeeCodeOf(record) {
  const hash = (Math.imul(record.id, HASH_MULTIPLIER) >>> 0).toString(16).toUpperCase()
  return `${CODE_PREFIX}${hash.padStart(CODE_LENGTH, '0').slice(-CODE_LENGTH)}`
}

export function committeeVisibility() {
  return assessmentSettings().committeeEntityDataVisibility
}

export function committeeEntityOf(record, visibility = committeeVisibility()) {
  const region = placeOfRegion(record.regionId)
  const entity = entityOf(record)
  if (visibility === 'code_only') return { visibility, code: committeeCodeOf(record), region }
  if (visibility === 'name_only') return { visibility, name: { ...entity.name }, region }
  return { visibility, ...entitySummary(entity), ...entityProfileOf(entity), region }
}

export function committeeLabelOf(record) {
  return committeeVisibility() === 'code_only' ? committeeCodeOf(record) : entityOf(record).name.ar
}
