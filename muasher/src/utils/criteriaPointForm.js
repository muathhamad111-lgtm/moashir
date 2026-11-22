import { SCORING_TYPES } from '@/constants/criteria'
import { attachmentsPayload } from '@/utils/criteriaAttachments'
import { normalizeWeight } from '@/utils/weight'

// Server field names → form field names, so a server error lands on its field.
export const POINT_FIELD_ALIASES = Object.freeze({
  'description.ar': 'descriptionAr',
  'description.en': 'descriptionEn',
  description: 'descriptionAr',
})

// The indicator form's values: from the record when editing, otherwise the defaults of its sub-criterion.
export function pointFormValues(point, { sub, defaultWeight }) {
  return point ? recordValues(point) : newPointValues(sub, defaultWeight)
}

function recordValues(point) {
  return {
    descriptionAr: point.description?.ar ?? '',
    descriptionEn: point.description?.en ?? '',
    weight: point.weight ?? '',
    scoringType: point.scoringType ?? SCORING_TYPES.SCORE,
    active: point.active,
  }
}

function newPointValues(sub, defaultWeight) {
  return {
    descriptionAr: '',
    descriptionEn: '',
    weight: defaultWeight,
    scoringType: sub.defaultPointScoringType ?? SCORING_TYPES.SCORE,
    active: true,
  }
}

// The request body. A descriptive indicator (directly evaluated sub-criterion) sends neither weight
// nor scoring type; the weight is also left out when it is edited elsewhere (equal split, sheet).
export function pointFormPayload(values, { attachments, descriptive, sendsWeight, isEdit }) {
  const payload = {
    description: { ar: values.descriptionAr.trim(), en: values.descriptionEn.trim() },
    attachments: attachmentsPayload(attachments),
  }
  if (!descriptive) payload.scoringType = values.scoringType
  if (sendsWeight) payload.weight = normalizeWeight(values.weight)
  return isEdit ? payload : { ...payload, active: values.active }
}
