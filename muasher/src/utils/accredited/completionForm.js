import { array, object, string } from 'yup'
import { ACCREDITED_LIMITS, COMPLETION_ITEM_KINDS, EXPIRY_OUTCOMES } from '@/constants/accredited'
import { EVALUATION_TYPES } from '@/constants/criteria'
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

const MAIN_PREFIX = 'main-'

function itemKey(subId, kind) {
  return `${subId}:${kind}`
}

function mainItemKey(mainId) {
  return `${MAIN_PREFIX}${mainId}:${COMPLETION_ITEM_KINDS.EVIDENCE}`
}

// A key names a sub-criterion item ("12:cv") or a main criterion's own attachments ("main-3:evidence").
function targetOf(key) {
  const [node, kind] = key.split(':')
  return node.startsWith(MAIN_PREFIX)
    ? { mainCriterionId: Number(node.slice(MAIN_PREFIX.length)), kind }
    : { subCriterionId: Number(node), kind }
}

export function completionPayload(values, notesByItem) {
  return {
    notes: values.notes.trim(),
    items: values.items.map((key) => {
      const note = (notesByItem[key] ?? '').trim()
      return { ...targetOf(key), ...(note ? { note } : {}) }
    }),
    deadlineAt: endOfRiyadhDay(values.deadline),
    expiryOutcome: values.expiryOutcome,
  }
}

function mainOptions(main) {
  if (!main.attachments?.length) return []
  return [{ key: mainItemKey(main.id), main, sub: null, kind: COMPLETION_ITEM_KINDS.EVIDENCE }]
}

// Per main criterion: its own attachments (when it asks for any), then each sub's CV and evidence.
export function itemOptions(criteria) {
  return (criteria?.mains ?? []).flatMap((main) => [
    ...mainOptions(main),
    ...main.subs.flatMap((sub) => [
      ...(sub.hasCv
        ? [{ key: itemKey(sub.id, COMPLETION_ITEM_KINDS.CV), sub, kind: COMPLETION_ITEM_KINDS.CV }]
        : []),
      {
        key: itemKey(sub.id, COMPLETION_ITEM_KINDS.EVIDENCE),
        sub,
        kind: COMPLETION_ITEM_KINDS.EVIDENCE,
      },
    ]),
  ])
}

// Below this percent an item is suggested for completion; «غير متحقق» counts as 0%.
const SUGGESTION_PERCENT = 50

function isLow(score) {
  return Boolean(score) && Number(score.percent) < SUGGESTION_PERCENT
}

function subIsLow(sub, scores) {
  if (sub.evaluationType === EVALUATION_TYPES.GUIDING_POINTS)
    return (sub.points ?? []).some((point) => isLow(scores.points?.[String(point.id)]))
  return isLow(scores.subs?.[String(sub.id)])
}

// The evidence of each sub-criterion with an item not achieved or below 50%, pre-selected as
// suggestions in the completion request (design §B.3.3).
export function suggestedItemKeys(criteria, scores) {
  if (!scores) return []
  return (criteria?.mains ?? []).flatMap((main) =>
    main.subs
      .filter((sub) => subIsLow(sub, scores))
      .map((sub) => itemKey(sub.id, COMPLETION_ITEM_KINDS.EVIDENCE)),
  )
}

// Which step of the request holds a field, so an error found later brings that step back.
const MESSAGE_FIELDS = ['notes', 'deadline', 'expiryOutcome']

export function completionStepOf(field) {
  return MESSAGE_FIELDS.includes(field.split('.')[0]) ? 'message' : 'items'
}
