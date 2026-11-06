import { object, string } from 'yup'
import { COMMITTEE_LIMITS as L, DISCUSSION_MODES } from '@/constants/committee'
import { endOfRiyadhDay } from '@/utils/datetime'

function bounded(t, { min = 0, max, required = false }) {
  const base = string().trim().max(max, t('award.common.maxChars', { max }))
  const ranged = min
    ? base.test('min', t('validation.minChars', { min }), (value) => !value || value.length >= min)
    : base
  return required ? ranged.required(t('validation.required')) : ranged
}

const FIELDS = {
  approve: (t) => ({ note: bounded(t, { max: L.SECRETARY_NOTE_MAX }) }),
  approveSubstitution: (t) => ({ note: bounded(t, { max: L.SECRETARY_NOTE_MAX }) }),
  returnToCommittee: (t) => ({
    generalNote: bounded(t, { min: L.RETURN_NOTE_MIN, max: L.RETURN_NOTE_MAX, required: true }),
  }),
  closeDiscussion: (t) => ({ summary: bounded(t, { max: L.SUMMARY_MAX }) }),
  openDiscussion: (t) => ({
    mode: string().oneOf(Object.values(DISCUSSION_MODES)).required(t('validation.required')),
    deadlineDate: string().when('mode', ([mode], schema) =>
      mode === DISCUSSION_MODES.SESSION ? schema.required(t('validation.required')) : schema,
    ),
    agreedOutcome: string().when('mode', ([mode], schema) =>
      mode === DISCUSSION_MODES.AGREED_OUTCOME
        ? bounded(t, { min: L.DISCUSSION_TEXT_MIN, max: L.DISCUSSION_TEXT_MAX, required: true })
        : schema,
    ),
  }),
}

export const DECISION_DEFAULTS = {
  approve: { note: '' },
  approveSubstitution: { note: '' },
  returnToCommittee: { generalNote: '' },
  closeDiscussion: { summary: '' },
  openDiscussion: { mode: DISCUSSION_MODES.SESSION, deadlineDate: '', agreedOutcome: '' },
}

export function decisionSchema(t, kind, asksReason) {
  const reason = bounded(t, { min: L.REASON_MIN, max: L.REASON_MAX, required: asksReason })
  return object({ ...FIELDS[kind](t), reason })
}

function compact(values) {
  return Object.fromEntries(
    Object.entries(values).filter(
      ([, value]) => value !== '' && value !== null && value !== undefined,
    ),
  )
}

export function decisionPayload(kind, values) {
  const trimmed = Object.fromEntries(
    Object.entries(values).map(([key, value]) => [
      key,
      typeof value === 'string' ? value.trim() : value,
    ]),
  )
  if (kind !== 'openDiscussion') return compact(trimmed)
  const { deadlineDate, agreedOutcome, ...rest } = trimmed
  if (trimmed.mode === DISCUSSION_MODES.SESSION)
    return compact({ ...rest, deadlineAt: endOfRiyadhDay(deadlineDate) })
  return compact({ ...rest, agreedOutcome })
}
