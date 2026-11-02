import { CRITERIA_NODE_TYPES as NODE } from '@/constants/criteria'

export const VERSION_TARGET = 'version'

function pair(ar = '', en = '') {
  const trimmed = { ar: ar.trim(), en: en.trim() }
  return trimmed.ar || trimmed.en ? trimmed : null
}

export function correctionValues(target) {
  const node = target.node ?? {}
  const primary =
    target.type === VERSION_TARGET
      ? node.label
      : target.type === NODE.POINT
        ? node.description
        : node.name
  return {
    primaryAr: primary?.ar ?? '',
    primaryEn: primary?.en ?? '',
    descriptionAr: node.description?.ar ?? '',
    descriptionEn: node.description?.en ?? '',
    noteAr: node.assessorNoteLabel?.ar ?? '',
    noteEn: node.assessorNoteLabel?.en ?? '',
  }
}

export function correctionAttachments(target) {
  return (target.node?.attachments ?? []).map((item) => ({
    id: item.id,
    ar: item.name?.ar ?? '',
    en: item.name?.en ?? '',
    original: item.name,
  }))
}

function attachmentNodes(attachments) {
  return attachments
    .filter((item) => item.ar.trim() !== item.original?.ar || item.en.trim() !== item.original?.en)
    .map((item) => ({
      type: NODE.ATTACHMENT,
      id: item.id,
      name: { ar: item.ar.trim(), en: item.en.trim() },
    }))
}

function nodeCorrection(target, values) {
  const node = { type: target.type, id: target.node.id }
  if (target.type === NODE.POINT)
    return { ...node, description: pair(values.primaryAr, values.primaryEn) }
  const correction = {
    ...node,
    name: pair(values.primaryAr, values.primaryEn),
    description: pair(values.descriptionAr, values.descriptionEn),
  }
  if (target.type === NODE.SUB) correction.assessorNoteLabel = pair(values.noteAr, values.noteEn)
  return correction
}

export function textCorrectionPayload(target, values, attachments, reason) {
  const trimmedReason = reason?.trim() || null
  if (target.type === VERSION_TARGET)
    return { label: pair(values.primaryAr, values.primaryEn), reason: trimmedReason }
  return {
    nodes: [nodeCorrection(target, values), ...attachmentNodes(attachments)],
    reason: trimmedReason,
  }
}

export function correctionFieldAliases(target) {
  const primary =
    target.type === VERSION_TARGET
      ? 'label'
      : target.type === NODE.POINT
        ? 'nodes.0.description'
        : 'nodes.0.name'
  const aliases = {
    [`${primary}.ar`]: 'primaryAr',
    [`${primary}.en`]: 'primaryEn',
    [primary]: 'primaryAr',
    'nodes.0.assessorNoteLabel.ar': 'noteAr',
    'nodes.0.assessorNoteLabel.en': 'noteEn',
  }
  if (target.type !== NODE.POINT) {
    Object.assign(aliases, {
      'nodes.0.description.ar': 'descriptionAr',
      'nodes.0.description.en': 'descriptionEn',
      'nodes.0.description': 'descriptionAr',
    })
  }
  return aliases
}
