import { CRITERIA_VERSION_STATUSES } from '@/constants/criteria'
import { ASSESSMENT_PATHS, SELF_REQUEST_PREFIX, SELF_STATUSES } from '@/constants/assessment'
import { selfAssessmentsFixture } from '../../fixtures/assessments'
import { findVersion, versionWithStatus } from '../criteriaStore'
import { versionTree } from '../criteriaSerializers'
import { findEntity, isDeleted } from '../entitiesStore'
import { computeSelfResult } from './selfScoring'
import { seedTransitions } from './statusHistory'
import { fixtureScores } from './fixtureScores'
import { accreditedSeedRecords } from '../accredited/accreditedSeed'
import { isAccreditedOpen } from '../accredited/accreditedStates'
import { advancedSeedRecords } from '../award/advancedSeed'
import { isAdvancedOpen } from '../award/advancedStates'
import { resetCycleStore } from '../award/cycleStore'

const REQUEST_NUMBER_DIGITS = 6
const RIYADH_OFFSET_MS = 3 * 60 * 60 * 1000

const MIN_NEXT_ID = 9100

const state = { records: [], nextId: MIN_NEXT_ID, sequences: {} }

export function boundTree(record) {
  const version = findVersion(record.criteriaVersionId)
  return version ? versionTree(version) : null
}

export function sheetOf(record) {
  return new Map(Object.entries(record.scores))
}

export function snapshotOf(record, tree) {
  return {
    version: 1,
    criteriaVersionId: record.criteriaVersionId,
    scaleKind: record.scaleKind,
    ...computeSelfResult(tree, sheetOf(record), record.scaleKind),
  }
}

function fixtureRecord(fixture, version) {
  const tree = versionTree(version)
  const record = {
    ...fixture,
    path: ASSESSMENT_PATHS.SELF,
    criteriaVersionId: version.id,
    scaleKind: version.scaleKind,
    scores: fixtureScores(tree, fixture.scoring),
    result: null,
  }
  delete record.scoring
  if (record.status === SELF_STATUSES.RESULT_AVAILABLE) record.result = snapshotOf(record, tree)
  record.transitions = seedTransitions(record)
  record.criteriaMoves = []
  return record
}

function sequenceKey(year, prefix) {
  return `${prefix}-${year}`
}

function numberSequenceOf(number) {
  const [prefix, year, sequence] = number.split('-')
  return { key: `${prefix}-${year}`, value: Number(sequence) }
}

export function resetAssessmentsStore() {
  const published = versionWithStatus(CRITERIA_VERSION_STATUSES.PUBLISHED)
  resetCycleStore()
  state.records = published
    ? [
        ...selfAssessmentsFixture.map((fixture) => fixtureRecord(fixture, published)),
        ...accreditedSeedRecords(published),
        ...advancedSeedRecords(published),
      ]
    : []
  state.nextId = Math.max(MIN_NEXT_ID, ...state.records.map((record) => record.id))
  state.sequences = {}
  state.records.forEach((record) => {
    const { key, value } = numberSequenceOf(record.number)
    state.sequences[key] = Math.max(state.sequences[key] ?? 0, value)
  })
}

resetAssessmentsStore()

export function allAssessments() {
  return state.records.filter((record) => findEntity(record.entityId))
}

export function findAssessment(id) {
  return allAssessments().find((record) => record.id === Number(id)) ?? null
}

export function entityOf(record) {
  return findEntity(record.entityId)
}

export function isFrozen(record) {
  const entity = entityOf(record)
  return Boolean(entity) && isDeleted(entity)
}

export function isOpen(record) {
  if (record.path === ASSESSMENT_PATHS.ACCREDITED) return isAccreditedOpen(record.status)
  if (record.path === ASSESSMENT_PATHS.ADVANCED) return isAdvancedOpen(record.status)
  return record.status === SELF_STATUSES.DRAFT
}

export function exposesResult(record) {
  if (record.path === ASSESSMENT_PATHS.ADVANCED) return false
  if (record.path === ASSESSMENT_PATHS.ACCREDITED) return record.status === 'completed' && record.result !== null
  return record.result !== null
}

export function openSelfDraftsOf(entityId) {
  return allAssessments().filter(
    (record) => record.path === ASSESSMENT_PATHS.SELF && record.entityId === Number(entityId) && isOpen(record),
  )
}

export function openRequestsOf(entityId) {
  return allAssessments().filter((record) => record.entityId === Number(entityId) && isOpen(record))
}

function riyadhYear(now) {
  return new Date(now.getTime() + RIYADH_OFFSET_MS).getUTCFullYear()
}

export function nextRequestNumber(now = new Date(), prefix = SELF_REQUEST_PREFIX) {
  const key = sequenceKey(riyadhYear(now), prefix)
  state.sequences[key] = (state.sequences[key] ?? 0) + 1
  return `${key}-${String(state.sequences[key]).padStart(REQUEST_NUMBER_DIGITS, '0')}`
}

export function insertAssessment(fields) {
  state.nextId += 1
  const record = { id: state.nextId, ...fields }
  state.records.push(record)
  return record
}

export function removeAssessment(id) {
  state.records = state.records.filter((record) => record.id !== Number(id))
}
