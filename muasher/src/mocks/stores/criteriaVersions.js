import { CRITERIA_VERSION_STATUSES as STATUS } from '@/constants/criteria'
import {
  ACTIVE_SCALE_KIND,
  attachmentsOf,
  deleteTreeOf,
  findVersion,
  highestVersionNumber,
  insertMain,
  insertPoint,
  insertSub,
  insertVersion,
  mainsOf,
  nextCriteriaId,
  pointsOf,
  replaceAttachments,
  subsOf,
  versionWithStatus,
} from './criteriaStore'
import { consistencyProblems } from './criteriaRules'
import { criteriaNotices } from './criteriaNotices'
import { reject } from './criteriaRejection'

function now() {
  return new Date().toISOString()
}

function copyAttachments(ownerType, fromId, toId) {
  replaceAttachments(ownerType, toId, attachmentsOf(ownerType, fromId))
}

function clonePoints(fromSub, toSub) {
  pointsOf(fromSub.id).forEach((point) => {
    const copy = insertPoint({
      ...point,
      id: nextCriteriaId(),
      subId: toSub.id,
      description: { ...point.description },
    })
    copyAttachments('point', point.id, copy.id)
  })
}

function cloneTree(source, draft) {
  mainsOf(source.id).forEach((main) => {
    const mainCopy = insertMain({
      ...main,
      id: nextCriteriaId(),
      versionId: draft.id,
      name: { ...main.name },
    })
    subsOf(main.id).forEach((sub) => {
      const subCopy = insertSub({
        ...sub,
        id: nextCriteriaId(),
        mainId: mainCopy.id,
        name: { ...sub.name },
      })
      copyAttachments('sub', sub.id, subCopy.id)
      clonePoints(sub, subCopy)
    })
  })
}

function cloneSource(cloneFromVersionId) {
  if (cloneFromVersionId === null || cloneFromVersionId === undefined)
    return versionWithStatus(STATUS.PUBLISHED)
  const source = findVersion(cloneFromVersionId)
  if (!source || source.status === STATUS.DRAFT) throw reject.cloneSourceInvalid()
  return source
}

export function refreshConsistency(version, actorId) {
  const wasConsistent = version.problems.length === 0
  version.problems = consistencyProblems(version.id)
  version.updatedAt = now()
  if (wasConsistent && version.problems.length) {
    criteriaNotices.becameInconsistent(actorId, version, version.problems.length)
  }
}

export function createDraft({ cloneFromVersionId = null, label = null }) {
  if (versionWithStatus(STATUS.DRAFT)) throw reject.draftExists()
  const source = cloneSource(cloneFromVersionId)
  const timestamp = now()
  const draft = insertVersion({
    id: nextCriteriaId(),
    versionNumber: highestVersionNumber() + 1,
    label: label ?? (source?.label ? { ...source.label } : null),
    status: STATUS.DRAFT,
    scaleKind: null,
    clonedFromVersionId: source?.id ?? null,
    publishedAt: null,
    archivedAt: null,
    createdAt: timestamp,
    updatedAt: timestamp,
    deleted: false,
    problems: [],
  })
  if (source) cloneTree(source, draft)
  draft.problems = consistencyProblems(draft.id)
  return draft
}

export function assertDraft(version) {
  if (version.status !== STATUS.DRAFT) throw reject.notDraft(version.status)
}

export function discardDraft(version) {
  assertDraft(version)
  deleteTreeOf(version.id)
  version.deleted = true
}

function signature(node, fields) {
  return JSON.stringify(fields.map((field) => node[field]))
}

const CHANGE_FIELDS = {
  main: ['name', 'description', 'weight', 'active', 'group'],
  sub: ['name', 'description', 'weight', 'active', 'evaluationType'],
  point: ['description', 'weight', 'active'],
}

const SCORE_AFFECTING_FIELDS = {
  main: ['weight', 'active'],
  sub: ['weight', 'active', 'evaluationType'],
  point: ['weight', 'active'],
}

function nodesByCode(versionId, fields) {
  const entries = []
  mainsOf(versionId).forEach((main) => {
    entries.push([main.code, signature(main, fields.main)])
    subsOf(main.id).forEach((sub) => {
      entries.push([sub.code, signature(sub, fields.sub)])
      pointsOf(sub.id).forEach((point) => entries.push([point.code, signature(point, fields.point)]))
    })
  })
  return new Map(entries)
}

function changedCodes(previous, draft, fields) {
  const before = nodesByCode(previous.id, fields)
  const after = nodesByCode(draft.id, fields)
  const codes = new Set([...before.keys(), ...after.keys()])
  return [...codes].filter((code) => before.get(code) !== after.get(code)).sort()
}

export function changeCount(previous, draft) {
  return changedCodes(previous, draft, CHANGE_FIELDS).length
}

export function scoreAffectingCodes(previous, draft) {
  return changedCodes(previous, draft, SCORE_AFFECTING_FIELDS)
}

export function editEffect(kind, scope) {
  const structural = kind === 'structural'
  return {
    kind,
    scope,
    reachesClosedAssessments: !structural,
    rebindsOpenAssessments: structural && scope !== 'new_only',
    keepsScoresOfUnchangedItems: !structural || scope === 'all',
    clearsAllOpenScores: structural && scope === 'reset_open',
    notifiesAffectedEntitiesAndAssessors: structural && scope === 'reset_open',
  }
}

export function markPublished(draft, previous) {
  const timestamp = now()
  if (previous)
    Object.assign(previous, {
      status: STATUS.ARCHIVED,
      archivedAt: timestamp,
      updatedAt: timestamp,
    })
  Object.assign(draft, {
    status: STATUS.PUBLISHED,
    scaleKind: ACTIVE_SCALE_KIND,
    publishedAt: timestamp,
    updatedAt: timestamp,
  })
}
