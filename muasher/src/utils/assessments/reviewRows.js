import { isFilled, scoreFieldName } from './scoreSheet'
import { slotGroupsOf } from '@/utils/accredited/evidenceRules'
import { SCORE_LEVELS } from '@/constants/assessment'
import { EVALUATION_TYPES } from '@/constants/criteria'

// The review workspace (design/admin-ux §B.3.1): each item's evidence sits under it, and each
// row shows whether it is scored.

export function unitOf(level, node) {
  return { level, id: node.id }
}

// A directly evaluated sub-criterion is one unit even when it lists descriptive indicators.
export function subUnits(sub) {
  if (sub.evaluationType === EVALUATION_TYPES.GUIDING_POINTS && sub.points?.length)
    return sub.points.map((point) => unitOf(SCORE_LEVELS.POINT, point))
  return [unitOf(SCORE_LEVELS.SUB, sub)]
}

export function isUnitScored(values, unit) {
  return isFilled(values?.[scoreFieldName(unit)])
}

export function scoredCount(values, units) {
  return units.filter((unit) => isUnitScored(values, unit)).length
}

// One row per requirement (with its files), keeping the slots that have files or are required.
// lateKeys: required attachments that came after the submission (D-5).
export function evidenceRowsOf(slots, files, lateKeys = []) {
  return (slots ?? [])
    .map((slot) => ({
      key: slot.key,
      name: slot.name,
      required: slot.required === true,
      late: lateKeys.includes(slot.key),
      files: files.filter((file) => file.attachmentKey === slot.key),
    }))
    .filter((row) => row.files.length || row.required)
}

// The sub-criterion's own requirements, each indicator's requirements, and the files that match
// no requirement (they stay visible, as «مرفقات أخرى»).
export function subEvidenceOf(sub, files, lateKeys = []) {
  const groups = slotGroupsOf(sub)
  const known = new Set(groups.flatMap((group) => group.slots.map((slot) => slot.key)))
  const byPoint = new Map(
    groups
      .filter((group) => group.point)
      .map((group) => [group.point.id, evidenceRowsOf(group.slots, files, lateKeys)]),
  )
  return {
    own: evidenceRowsOf(groups[0].slots, files, lateKeys),
    byPoint,
    others: files.filter((file) => !known.has(file.attachmentKey)),
  }
}
