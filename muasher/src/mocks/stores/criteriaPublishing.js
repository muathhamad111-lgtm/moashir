import {
  CRITERIA_EDIT_SCOPE_ORDER,
  CRITERIA_NODE_TYPES as NODE,
  CRITERIA_VERSION_STATUSES as STATUS,
} from '@/constants/criteria'
import { criteriaProblemMessages } from '../fixtures/criteriaMessages'
import {
  findAttachment,
  findMain,
  findPoint,
  findSub,
  mainOfSub,
  recordEditEvent,
  subOfPoint,
  versionWithStatus,
} from './criteriaStore'
import { consistencyProblems, problemFieldKey } from './criteriaRules'
import {
  assertDraft,
  changeCount,
  editEffect,
  markPublished,
  scoreAffectingCodes,
} from './criteriaVersions'
import { announceCriteriaEdit, openAssessmentsBoundTo } from './criteriaEditHooks'
import { criteriaNotices } from './criteriaNotices'
import { CriteriaRejection, fieldMessage, reject } from './criteriaRejection'
import { FieldCollector, readDescription, readLocalized, readReason } from './criteriaValidation'

const TEXT_FIELDS = {
  [NODE.MAIN]: ['name', 'description'],
  [NODE.SUB]: ['name', 'description', 'assessorNoteLabel'],
  [NODE.POINT]: ['description'],
  [NODE.ATTACHMENT]: ['name'],
}

function problemMessage(entry) {
  const template = criteriaProblemMessages[entry.problem]
  return {
    ar: template.ar,
    en: template.en,
    params: {
      code: entry.code ?? '',
      expected: entry.expectedWeight ?? '',
      actual: entry.actualWeight ?? '',
    },
  }
}

function inconsistent(problems) {
  const fields = {}
  problems.forEach((entry) => {
    const key = problemFieldKey(entry)
    fields[key] = [...(fields[key] ?? []), problemMessage(entry)]
  })
  return new CriteriaRejection(422, 'criteria.weights_inconsistent', { fields, meta: { problems } })
}

function assertReason(actor, reason) {
  if (actor.isSuperAdmin && !reason) {
    throw new CriteriaRejection(422, 'reason_required', {
      fields: { reason: [fieldMessage('reason_required')] },
    })
  }
}

function readPublishInstruction(body) {
  const errors = new FieldCollector()
  if (body.confirm !== true) errors.add('confirm', 'field.required')
  if (
    body.scope !== undefined &&
    body.scope !== null &&
    !CRITERIA_EDIT_SCOPE_ORDER.includes(body.scope)
  ) {
    errors.add('scope', 'field.invalid')
  }
  const reason = readReason(errors, body)
  errors.assertValid()
  return { scope: body.scope ?? null, reason }
}

export function publishVersion(actor, version, body) {
  const instruction = readPublishInstruction(body)
  assertDraft(version)
  const problems = consistencyProblems(version.id)
  if (problems.length) throw inconsistent(problems)
  const previous = versionWithStatus(STATUS.PUBLISHED)
  if (previous && !instruction.scope) throw reject.scopeRequired()
  if (previous) assertReason(actor, instruction.reason)
  const count = previous ? changeCount(previous, version) : 0
  const rescoredCodes = previous ? scoreAffectingCodes(previous, version) : []
  const effect = editEffect('structural', instruction.scope)
  const affectedOpenCount =
    previous && effect.rebindsOpenAssessments ? openAssessmentsBoundTo(previous.id) : 0
  markPublished(version, previous)
  version.problems = []
  if (!previous)
    return {
      version,
      meta: { editEventId: null, affectedOpenCount: 0, changeCount: 0, effect: null },
    }
  const event = recordEditEvent({
    kind: 'structural',
    scope: instruction.scope,
    fromVersionId: previous.id,
    toVersionId: version.id,
    affectedOpenCount,
    changeCount: count,
    rescoredCodes,
    reason: instruction.reason,
    actorId: actor.id,
  })
  criteriaNotices.publishedEdited(actor.id, count, instruction.scope)
  announceCriteriaEdit(event)
  return {
    version,
    meta: {
      editEventId: event.id,
      affectedOpenCount,
      changeCount: count,
      effect,
    },
  }
}

function inVersion(versionId, main) {
  return Boolean(main) && main.versionId === versionId
}

function locateNode(version, type, id) {
  if (type === NODE.MAIN) return inVersion(version.id, findMain(id)) ? findMain(id) : null
  if (type === NODE.SUB)
    return findSub(id) && inVersion(version.id, mainOfSub(findSub(id))) ? findSub(id) : null
  if (type === NODE.POINT) {
    const point = findPoint(id)
    return point && inVersion(version.id, mainOfSub(subOfPoint(point))) ? point : null
  }
  const attachment = findAttachment(id)
  if (!attachment) return null
  const owner =
    attachment.ownerType === 'sub' ? findSub(attachment.ownerId) : findPoint(attachment.ownerId)
  const sub = attachment.ownerType === 'sub' ? owner : subOfPoint(owner)
  return inVersion(version.id, mainOfSub(sub)) ? attachment : null
}

function readTextField(errors, node, field) {
  if (field === 'description') return readDescription(errors, node, field, node.type === NODE.POINT)
  return readLocalized(errors, node, field)
}

function readCorrection(errors, node, index) {
  const fields = TEXT_FIELDS[node.type] ?? []
  if (!fields.length) errors.add(`nodes.${index}.type`, 'field.invalid')
  const values = {}
  fields.forEach((field) => {
    const nested = new FieldCollector()
    const value = readTextField(nested, node, field)
    if (Object.keys(nested.fields).length)
      errors.add(`nodes.${index}.${field}`, 'criteria.text_required')
    if (value !== undefined) values[field] = value
  })
  return values
}

function applyText(target, values) {
  return Object.entries(values).filter(([field, value]) => {
    if (JSON.stringify(target[field] ?? null) === JSON.stringify(value)) return false
    target[field] = value
    return true
  }).length
}

export function correctTexts(actor, version, body) {
  if (version.status === STATUS.DRAFT) throw reject.isDraft()
  const errors = new FieldCollector()
  const label = readLocalized(errors, body, 'label')
  const nodes = Array.isArray(body.nodes) ? body.nodes : []
  if (!label && !nodes.length) errors.add('nodes', 'field.required')
  const corrections = nodes.map((node, index) => ({
    node,
    values: readCorrection(errors, node, index),
    index,
  }))
  const reason = readReason(errors, body)
  errors.assertValid()
  assertReason(actor, reason)
  const targets = corrections.map(({ node, values, index }) => {
    const target = locateNode(version, node.type, node.id)
    if (!target) throw reject.nodeNotInVersion(index)
    return { target, values }
  })
  const count =
    (label ? applyText(version, { label }) : 0) +
    targets.reduce((total, { target, values }) => total + applyText(target, values), 0)
  const effect = editEffect('text_correction', 'all')
  if (!count) return { version, meta: { editEventId: null, changeCount: 0, effect } }
  const event = recordEditEvent({
    kind: 'text_correction',
    scope: 'all',
    fromVersionId: version.id,
    toVersionId: null,
    changeCount: count,
    reason,
    actorId: actor.id,
  })
  criteriaNotices.publishedEdited(actor.id, count, 'all')
  return { version, meta: { editEventId: event.id, changeCount: count, effect } }
}
