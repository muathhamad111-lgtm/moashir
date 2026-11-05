import { array, object, string } from 'yup'
import { ACCREDITED_LIMITS, COMPLETION_ITEM_KINDS, EXPIRY_OUTCOMES } from '@/constants/accredited'
import { endOfRiyadhDay, riyadhDateKey } from '@/utils/datetime'

const DAY_MS = 24 * 60 * 60 * 1000

export function deadlineBounds(now = new Date()) {
  return {
    min: riyadhDateKey(now),
    max: riyadhDateKey(
      new Date(now.getTime() + (ACCREDITED_LIMITS.COMPLETION_DEADLINE_MAX_DAYS - 1) * DAY_MS),
    ),
  }
}

export function completionSchema(
  t,
  bounds,
  deadlineMessage = t('review.completion.validation.deadline', {
    days: ACCREDITED_LIMITS.COMPLETION_DEADLINE_MAX_DAYS,
  }),
) {
  return object({
    notes: string()
      .trim()
      .required(t('validation.required'))
      .min(
        ACCREDITED_LIMITS.COMPLETION_NOTES_MIN,
        t('validation.minChars', { min: ACCREDITED_LIMITS.COMPLETION_NOTES_MIN }),
      )
      .max(
        ACCREDITED_LIMITS.COMPLETION_NOTES_MAX,
        t('entities.validation.maxChars', { max: ACCREDITED_LIMITS.COMPLETION_NOTES_MAX }),
      ),
    deadline: string()
      .required(t('validation.required'))
      .test(
        'window',
        deadlineMessage,
        (value) => !value || (value >= bounds.min && value <= bounds.max),
      ),
    expiryOutcome: string()
      .required(t('validation.required'))
      .oneOf(Object.values(EXPIRY_OUTCOMES)),
    items: array().min(1, t('review.completion.validation.items')),
  })
}

function itemKey(subId, kind) {
  return `${subId}:${kind}`
}

export function completionPayload(values, notesByItem) {
  return {
    notes: values.notes.trim(),
    items: values.items.map((key) => {
      const [subCriterionId, kind] = key.split(':')
      const note = (notesByItem[key] ?? '').trim()
      return { subCriterionId: Number(subCriterionId), kind, ...(note ? { note } : {}) }
    }),
    deadlineAt: endOfRiyadhDay(values.deadline),
    expiryOutcome: values.expiryOutcome,
  }
}

export function itemOptions(criteria) {
  return (criteria?.mains ?? []).flatMap((main) =>
    main.subs.flatMap((sub) => [
      ...(sub.hasCv
        ? [{ key: itemKey(sub.id, COMPLETION_ITEM_KINDS.CV), sub, kind: COMPLETION_ITEM_KINDS.CV }]
        : []),
      {
        key: itemKey(sub.id, COMPLETION_ITEM_KINDS.EVIDENCE),
        sub,
        kind: COMPLETION_ITEM_KINDS.EVIDENCE,
      },
    ]),
  )
}
