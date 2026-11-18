import {
  CRITERIA_EDIT_SCOPE_ORDER,
  CRITERIA_EDIT_SCOPES,
  CRITERIA_VERSION_STATUSES as STATUS,
} from '@/constants/criteria'
import { fromHundredths } from '@/utils/weight'
import { mainsOf, pointsOf, subsOf, versionWithStatus } from './criteriaStore'
import { openAssessmentsBoundTo } from './criteriaEditHooks'
import { assertDraft } from './criteriaVersions'

const NODE_FIELDS = {
  main: {
    name: 'name',
    description: 'description',
    weight: 'weight',
    active: 'active',
    group: 'group',
    order: 'order',
  },
  sub: {
    name: 'name',
    description: 'description',
    weight: 'weight',
    active: 'active',
    evaluation_type: 'evaluationType',
    has_cv: 'hasCv',
    order: 'order',
  },
  point: { description: 'description', weight: 'weight', active: 'active', order: 'order' },
}

const SCORE_AFFECTING = ['weight', 'evaluation_type', 'active']

function fieldValue(node, key) {
  const value = node[key] ?? null
  return key === 'weight' && value !== null ? fromHundredths(value) : value
}

function entry(type, node, label) {
  const fields = Object.fromEntries(
    Object.entries(NODE_FIELDS[type]).map(([field, key]) => [field, fieldValue(node, key)]),
  )
  return { type, id: node.id, code: node.code, label, fields }
}

function nodesOf(versionId) {
  const nodes = new Map()
  mainsOf(versionId).forEach((main) => {
    nodes.set(`main:${main.code}`, entry('main', main, main.name))
    subsOf(main.id).forEach((sub) => {
      nodes.set(`sub:${sub.code}`, entry('sub', sub, sub.name))
      pointsOf(sub.id).forEach((point) =>
        nodes.set(`point:${point.code}`, entry('point', point, point.description)),
      )
    })
  })
  return nodes
}

function changedFields(before, after) {
  return Object.keys(after.fields).filter(
    (field) => JSON.stringify(before.fields[field]) !== JSON.stringify(after.fields[field]),
  )
}

function pick(values, fields) {
  return Object.fromEntries(fields.map((field) => [field, values[field]]))
}

function change(before, after) {
  if (!before) return { kind: 'added', node: after, fields: [] }
  if (!after) return { kind: 'removed', node: before, fields: [] }
  const fields = changedFields(before, after)
  return fields.length ? { kind: 'modified', node: after, fields } : null
}

function changeResource(before, after) {
  const found = change(before, after)
  if (!found) return null
  const scoreAffecting =
    found.kind !== 'modified' || found.fields.some((field) => SCORE_AFFECTING.includes(field))
  return {
    nodeType: found.node.type,
    code: found.node.code,
    change: found.kind,
    fromNodeId: before?.id ?? null,
    toNodeId: after?.id ?? null,
    fields: found.fields,
    before: before ? pick(before.fields, found.fields) : {},
    after: after ? pick(after.fields, found.fields) : {},
    scoreAffecting: scoreAffecting,
    label: found.node.label ?? null,
  }
}

function reboundPerScope(open) {
  return CRITERIA_EDIT_SCOPE_ORDER.map((scope) => ({
    scope,
    count: scope === CRITERIA_EDIT_SCOPES.NEW_ONLY ? 0 : open,
  }))
}

export function publicationPreview(draft) {
  assertDraft(draft)
  const published = versionWithStatus(STATUS.PUBLISHED)
  if (!published) {
    return {
      replaces: null,
      changeCount: 0,
      scoreAffectingCount: 0,
      changes: [],
      reboundOpenAssessments: reboundPerScope(0),
    }
  }
  const before = nodesOf(published.id)
  const after = nodesOf(draft.id)
  const keys = [...new Set([...after.keys(), ...before.keys()])]
  const changes = keys.map((key) => changeResource(before.get(key), after.get(key))).filter(Boolean)
  return {
    replaces: { id: published.id, versionNumber: published.versionNumber },
    changeCount: changes.length,
    scoreAffectingCount: changes.filter((item) => item.scoreAffecting).length,
    changes,
    reboundOpenAssessments: reboundPerScope(openAssessmentsBoundTo(published.id)),
  }
}
