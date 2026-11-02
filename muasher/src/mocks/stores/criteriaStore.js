import { CRITERIA_VERSION_STATUSES as STATUS, EVALUATION_TYPES } from '@/constants/criteria'
import { SCALE_KINDS } from '@/constants/assessment'
import { toHundredths } from '@/utils/weight'
import {
  CRITERIA_SEED_PUBLISHED_AT,
  criteriaSeedFixture,
  criteriaSeedLabel,
} from '../fixtures/criteriaSeed'

export const ACTIVE_SCALE_KIND = SCALE_KINDS.OF_10

const SEED_VERSION_ID = 1
const SUB_IDS_PER_MAIN = 100
const FIRST_POINT_ID = 1000
const POINT_IDS_PER_SUB = 100

const state = {
  versions: [],
  mains: [],
  subs: [],
  points: [],
  attachments: [],
  editEvents: [],
  usedCodes: new Set(),
}
let sequence = 200000

export function nextCriteriaId() {
  sequence += 1
  return sequence
}

function claimCode(code) {
  state.usedCodes.add(code)
  return code
}

export function nextCode(prefix) {
  let index = 1
  while (state.usedCodes.has(`${prefix}${index}`)) index += 1
  return claimCode(`${prefix}${index}`)
}

function seedAttachments(ownerType, ownerId, items) {
  items.forEach((item, index) => {
    state.attachments.push({
      id: nextCriteriaId(),
      ownerType,
      ownerId,
      key: `${ownerType}_${ownerId}_${index + 1}`,
      name: { ...item.name },
      required: item.required,
      order: index + 1,
    })
  })
}

function seedPoints(sub, points, guidingSequence) {
  points.forEach((point, index) => {
    const id = FIRST_POINT_ID + guidingSequence * POINT_IDS_PER_SUB + index + 1
    state.points.push({
      id,
      subId: sub.id,
      code: claimCode(`${sub.code}.${index + 1}`),
      description: { ...point.description },
      weight: toHundredths(point.weight),
      active: true,
      order: index + 1,
    })
    seedAttachments('point', id, point.attachments)
  })
}

function seedTree() {
  let guidingSequence = 0
  criteriaSeedFixture.forEach((seedMain, mainIndex) => {
    const main = {
      id: mainIndex + 1,
      versionId: SEED_VERSION_ID,
      code: claimCode(`M${mainIndex + 1}`),
      name: { ...seedMain.name },
      description: { ...seedMain.description },
      weight: toHundredths(seedMain.weight),
      distributeEqually: seedMain.distributeEqually,
      group: seedMain.group,
      active: true,
      order: mainIndex + 1,
    }
    state.mains.push(main)
    seedMain.subs.forEach((seedSub, subIndex) => {
      const sub = {
        id: main.id * SUB_IDS_PER_MAIN + subIndex + 1,
        mainId: main.id,
        code: claimCode(`${main.code}.${subIndex + 1}`),
        name: { ...seedSub.name },
        description: { ...seedSub.description },
        weight: toHundredths(seedSub.weight),
        distributeEqually: seedSub.distributeEqually,
        evaluationType: seedSub.evaluationType,
        hasCv: seedSub.hasCv,
        assessorNoteLabel: null,
        active: true,
        order: subIndex + 1,
      }
      state.subs.push(sub)
      seedAttachments('sub', sub.id, seedSub.attachments)
      if (sub.evaluationType === EVALUATION_TYPES.GUIDING_POINTS && seedSub.points.length) {
        seedPoints(sub, seedSub.points, guidingSequence)
        guidingSequence += 1
      }
    })
  })
}

export function resetCriteriaStore() {
  Object.assign(state, {
    versions: [],
    mains: [],
    subs: [],
    points: [],
    attachments: [],
    editEvents: [],
    usedCodes: new Set(),
  })
  sequence = 200000
  state.versions.push({
    id: SEED_VERSION_ID,
    versionNumber: 1,
    label: { ...criteriaSeedLabel },
    status: STATUS.PUBLISHED,
    scaleKind: ACTIVE_SCALE_KIND,
    clonedFromVersionId: null,
    publishedAt: CRITERIA_SEED_PUBLISHED_AT,
    archivedAt: null,
    createdAt: CRITERIA_SEED_PUBLISHED_AT,
    updatedAt: CRITERIA_SEED_PUBLISHED_AT,
    deleted: false,
    problems: [],
  })
  seedTree()
}

resetCriteriaStore()

const byOrder = (left, right) => left.order - right.order

export function liveVersions() {
  return state.versions
    .filter((version) => !version.deleted)
    .sort((a, b) => b.versionNumber - a.versionNumber)
}

export function findVersion(id) {
  return liveVersions().find((version) => version.id === Number(id)) ?? null
}

export function versionWithStatus(status) {
  return liveVersions().find((version) => version.status === status) ?? null
}

export function highestVersionNumber() {
  return state.versions.reduce((highest, version) => Math.max(highest, version.versionNumber), 0)
}

export function mainsOf(versionId) {
  return state.mains.filter((main) => main.versionId === versionId).sort(byOrder)
}

export function subsOf(mainId) {
  return state.subs.filter((sub) => sub.mainId === mainId).sort(byOrder)
}

export function pointsOf(subId) {
  return state.points.filter((point) => point.subId === subId).sort(byOrder)
}

export function attachmentsOf(ownerType, ownerId) {
  return state.attachments
    .filter((item) => item.ownerType === ownerType && item.ownerId === ownerId)
    .sort(byOrder)
}

export function findMain(id) {
  return state.mains.find((main) => main.id === Number(id)) ?? null
}

export function findSub(id) {
  return state.subs.find((sub) => sub.id === Number(id)) ?? null
}

export function findPoint(id) {
  return state.points.find((point) => point.id === Number(id)) ?? null
}

export function findAttachment(id) {
  return state.attachments.find((item) => item.id === Number(id)) ?? null
}

export function versionOfMain(main) {
  return findVersion(main.versionId)
}

export function mainOfSub(sub) {
  return findMain(sub.mainId)
}

export function subOfPoint(point) {
  return findSub(point.subId)
}

export function insertVersion(version) {
  state.versions.push(version)
  return version
}

export function insertMain(main) {
  state.mains.push(main)
  return main
}

export function insertSub(sub) {
  state.subs.push(sub)
  return sub
}

export function insertPoint(point) {
  state.points.push(point)
  return point
}

export function replaceAttachments(ownerType, ownerId, items) {
  state.attachments = state.attachments.filter(
    (item) => !(item.ownerType === ownerType && item.ownerId === ownerId),
  )
  items.forEach((item, index) => {
    state.attachments.push({
      id: nextCriteriaId(),
      ownerType,
      ownerId,
      key: item.key || `${ownerType}_${ownerId}_${nextCriteriaId()}`,
      name: { ar: item.name?.ar ?? '', en: item.name?.en ?? '' },
      required: Boolean(item.required),
      order: index + 1,
    })
  })
}

export function deletePoint(pointId) {
  state.points = state.points.filter((point) => point.id !== pointId)
  replaceAttachments('point', pointId, [])
}

export function deleteSub(subId) {
  pointsOf(subId).forEach((point) => deletePoint(point.id))
  state.subs = state.subs.filter((sub) => sub.id !== subId)
  replaceAttachments('sub', subId, [])
}

export function deleteMain(mainId) {
  subsOf(mainId).forEach((sub) => deleteSub(sub.id))
  state.mains = state.mains.filter((main) => main.id !== mainId)
}

export function deleteTreeOf(versionId) {
  mainsOf(versionId).forEach((main) => deleteMain(main.id))
}

export function recordEditEvent(event) {
  const recorded = {
    id: state.editEvents.length + 1,
    createdAt: new Date().toISOString(),
    ...event,
  }
  state.editEvents.push(recorded)
  return recorded
}

export function editEvents() {
  return [...state.editEvents]
}
