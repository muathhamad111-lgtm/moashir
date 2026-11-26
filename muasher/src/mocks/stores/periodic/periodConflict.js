import { HttpResponse } from 'msw'
import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { currentPeriod, nextPeriod } from './periodCalendar'
import { periodStatusOf } from './requestPeriods'

const CONFLICT = 409
const DRAFT = 'draft'
const MESSAGES = {
  ar: 'تم تقييم جهتك في هذه الفترة. يمكنك الإرسال ابتداءً من {date}.',
  en: 'Your entity is assessed in this period. You can submit from {date}.',
}

export function nextSubmissionGate(entityId, now = new Date()) {
  const period = currentPeriod(now)
  if (!period || periodStatusOf(entityId, period, now).status !== 'assessed') return null
  return { currentPeriod: period, nextPeriod: nextPeriod(now) }
}

export function periodConflictFor(record, locale = 'ar') {
  if (record.path !== ASSESSMENT_PATHS.ACCREDITED || record.status !== DRAFT) return null
  const gate = nextSubmissionGate(record.entityId)
  if (!gate) return null
  const date = gate.nextPeriod.startsOn
  return HttpResponse.json(
    {
      data: null,
      meta: { nextSubmissionFrom: date, ...gate },
      message: (MESSAGES[locale] ?? MESSAGES.ar).replace('{date}', date),
      code: 'period.already_assessed',
      errors: {},
    },
    { status: CONFLICT },
  )
}
