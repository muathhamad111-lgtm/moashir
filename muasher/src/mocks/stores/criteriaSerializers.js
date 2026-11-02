import { CRITERIA_VERSION_STATUSES as STATUS } from '@/constants/criteria'
import { fromHundredths } from '@/utils/weight'
import {
  attachmentsOf,
  mainOfSub,
  mainsOf,
  pointsOf,
  subsOf,
  versionWithStatus,
} from './criteriaStore'
import { activeTotal, scoresGuidingPoints } from './criteriaRules'

const PUBLIC_MODEL_FIELDS = ['id', 'code', 'order', 'group', 'name', 'description']

function attachmentResource(item) {
  return {
    id: item.id,
    key: item.key,
    name: { ...item.name },
    required: item.required,
    order: item.order,
  }
}

function treeAttachment(item) {
  return { key: item.key, name: { ...item.name }, required: item.required }
}

export function versionResource(version) {
  return {
    id: version.id,
    versionNumber: version.versionNumber,
    label: version.label ? { ...version.label } : null,
    status: version.status,
    scaleKind: version.scaleKind,
    consistent: version.problems.length === 0,
    clonedFromVersionId: version.clonedFromVersionId,
    publishedAt: version.publishedAt,
    archivedAt: version.archivedAt,
    createdAt: version.createdAt,
    updatedAt: version.updatedAt,
  }
}

export function versionSummary(version) {
  return version
    ? {
        id: version.id,
        versionNumber: version.versionNumber,
        status: version.status,
        label: version.label,
      }
    : null
}

export function mainResource(main) {
  const subs = subsOf(main.id)
  const allocated = activeTotal(subs)
  return {
    id: main.id,
    versionId: main.versionId,
    code: main.code,
    name: { ...main.name },
    description: main.description ? { ...main.description } : null,
    weight: fromHundredths(main.weight),
    distributeEqually: main.distributeEqually,
    group: main.group,
    active: main.active,
    order: main.order,
    subCount: subs.length,
    allocatedWeight: fromHundredths(allocated),
    remainingWeight: fromHundredths(main.weight - allocated),
    weightBalanced: allocated === main.weight,
  }
}

export function subResource(sub) {
  const main = mainOfSub(sub)
  const points = pointsOf(sub.id)
  const allocatedToPoints = activeTotal(points)
  return {
    id: sub.id,
    mainCriterionId: sub.mainId,
    code: sub.code,
    name: { ...sub.name },
    description: sub.description ? { ...sub.description } : null,
    weight: fromHundredths(sub.weight),
    distributeEqually: sub.distributeEqually,
    evaluationType: sub.evaluationType,
    hasCv: sub.hasCv,
    assessorNoteLabel: sub.assessorNoteLabel ? { ...sub.assessorNoteLabel } : null,
    attachments: attachmentsOf('sub', sub.id).map(attachmentResource),
    active: sub.active,
    order: sub.order,
    availableWeight: fromHundredths(main.weight - activeTotal(subsOf(main.id), sub.id)),
    parentName: { ...main.name },
    pointCount: points.length,
    allocatedPointWeight: fromHundredths(allocatedToPoints),
    remainingPointWeight: fromHundredths(sub.weight - allocatedToPoints),
  }
}

export function pointResource(point) {
  return {
    id: point.id,
    subCriterionId: point.subId,
    code: point.code,
    description: { ...point.description },
    weight: fromHundredths(point.weight),
    active: point.active,
    order: point.order,
    attachments: attachmentsOf('point', point.id).map(attachmentResource),
  }
}

function treePoint(point) {
  return {
    id: point.id,
    code: point.code,
    order: point.order,
    description: { ...point.description },
    weight: fromHundredths(point.weight),
    attachments: attachmentsOf('point', point.id).map(treeAttachment),
  }
}

function treeSub(sub) {
  return {
    id: sub.id,
    code: sub.code,
    order: sub.order,
    name: { ...sub.name },
    description: sub.description ? { ...sub.description } : null,
    weight: fromHundredths(sub.weight),
    evaluationType: sub.evaluationType,
    hasCv: sub.hasCv,
    assessorNoteLabel: sub.assessorNoteLabel ? { ...sub.assessorNoteLabel } : null,
    attachments: attachmentsOf('sub', sub.id).map(treeAttachment),
    points: scoresGuidingPoints(sub)
      ? pointsOf(sub.id)
          .filter((point) => point.active)
          .map(treePoint)
      : [],
  }
}

function treeMain(main) {
  return {
    id: main.id,
    code: main.code,
    order: main.order,
    group: main.group,
    name: { ...main.name },
    description: main.description ? { ...main.description } : null,
    weight: fromHundredths(main.weight),
    subs: subsOf(main.id)
      .filter((sub) => sub.active)
      .map(treeSub),
  }
}

export function versionTree(version) {
  const mains = mainsOf(version.id)
  return {
    versionId: version.id,
    versionNumber: version.versionNumber,
    label: version.label ? { ...version.label } : null,
    status: version.status,
    scaleKind: version.scaleKind,
    publishedAt: version.publishedAt,
    totalWeight: fromHundredths(activeTotal(mains)),
    mains: mains.filter((main) => main.active).map(treeMain),
  }
}

export function publishedTree() {
  const published = versionWithStatus(STATUS.PUBLISHED)
  return published ? versionTree(published) : null
}

export function publicModelCriteria() {
  const tree = publishedTree()
  if (!tree) return []
  return tree.mains.map((main) =>
    Object.fromEntries(PUBLIC_MODEL_FIELDS.map((field) => [field, main[field]])),
  )
}
