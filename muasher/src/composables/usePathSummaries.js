import { computed, toValue } from 'vue'
import { useI18n } from 'vue-i18n'
import { PATH_KEYS } from '@/constants/entityPaths'
import { localizedText } from '@/utils/localizedText'
import { isoDate } from '@/utils/datetime'
import { isolate } from '@/utils/bidi'
import { percentText } from '@/utils/percentText'
import { statusLabelKey } from '@/utils/canonicalStatus'
import {
  ACCREDITED_STATES as A,
  AWARD_STATES as W,
  SELF_STATES as S,
  accreditedStateOf,
  awardStateOf,
  selfStateOf,
} from '@/utils/pathStates'
import { accreditedAction, awardAction, selfAction } from '@/utils/pathActions'

const ROOT = 'paths.summary'

function useText() {
  const { t, n, locale } = useI18n()
  return {
    t,
    label: (content) => localizedText(content, locale.value),
    percent: (value) =>
      value === null || value === undefined ? '' : isolate(percentText(n, t, value)),
  }
}

function awaitingLine(payment, text) {
  return text.t(`${ROOT}.awaitingPayment`, { reference: isolate(payment.reference) })
}

function selfStatus(self, state, text) {
  if (self.openDraft)
    return text.t(`${ROOT}.self.draft`, { number: isolate(self.openDraft.number) })
  if (state === S.AWAITING_PAYMENT) return awaitingLine(self.awaitingPayment, text)
  if (state === S.UNAVAILABLE) return text.t(`${ROOT}.unavailable`)
  if (!self.lastResult) return text.t(`${ROOT}.self.noResult`)
  return text.t(`${ROOT}.self.lastResult`, {
    percent: text.percent(self.lastResult.percent),
    date: isolate(isoDate(self.lastResult.completedAt)),
  })
}

function selfSummary(self, text) {
  const state = selfStateOf(self)
  if (state === S.HIDDEN) return { key: PATH_KEYS.SELF, state, lines: [], status: '' }
  return {
    key: PATH_KEYS.SELF,
    state,
    status: selfStatus(self, state, text),
    lines: [],
    action: selfAction(state, self),
  }
}

function accreditedPeriodLine(accredited, text) {
  const period = accredited.currentPeriod
  if (accredited.isLive && period)
    return text.t(`${ROOT}.accredited.period`, {
      period: text.label(period.label),
      date: isolate(period.endsOn),
    })
  if (accredited.nextPeriod)
    return text.t(`${ROOT}.accredited.rollout`, { date: isolate(accredited.nextPeriod.startsOn) })
  return ''
}

const ACCREDITED_STATUS_LINES = {
  [A.AWAITING_PAYMENT]: (path, text) => awaitingLine(path.awaitingPayment, text),
  [A.NEEDS_COMPLETION]: (path, text) => openRequestLine(path, text),
  [A.DRAFT]: (path, text) => openRequestLine(path, text),
  [A.IN_REVIEW]: (path, text) => openRequestLine(path, text),
  [A.ASSESSED]: (path, text) =>
    text.t(`${ROOT}.accredited.assessed`, { date: isolate(path.nextSubmissionFrom ?? '') }),
  [A.UNAVAILABLE]: (path, text) => text.t(`${ROOT}.unavailable`),
  [A.APPLY]: (path, text) => text.t(`${ROOT}.accredited.notSubmitted`),
}

function openRequestLine(path, text) {
  return text.t(`${ROOT}.accredited.openRequest`, {
    number: isolate(path.openRequest.number),
    status: text.t(statusLabelKey(path.openRequest.status)),
  })
}

function kitLine(accredited, text) {
  const status = accredited?.kit?.status
  return status ? text.t(`${ROOT}.kit.${status}`) : ''
}

function accreditedSummary(accredited, text) {
  const state = accreditedStateOf(accredited)
  if (state === A.HIDDEN) return { key: PATH_KEYS.ACCREDITED, state, lines: [], status: '' }
  return {
    key: PATH_KEYS.ACCREDITED,
    state,
    status: ACCREDITED_STATUS_LINES[state](accredited, text),
    lines: [accreditedPeriodLine(accredited, text)].filter(Boolean),
    kit: kitLine(accredited, text),
    action: accreditedAction(state, accredited),
  }
}

function awardWindowLine(award, text) {
  const cycle = text.label(award.cycle?.name)
  const keys = {
    open: () =>
      text.t(`${ROOT}.award.windowOpen`, {
        cycle,
        date: isolate(isoDate(award.cycle.submissionEnd)),
      }),
    upcoming: () =>
      text.t(`${ROOT}.award.windowUpcoming`, {
        cycle,
        date: isolate(isoDate(award.cycle.submissionStart)),
      }),
    closed: () => text.t(`${ROOT}.award.windowClosed`, { cycle }),
    none: () => text.t(`${ROOT}.award.noCycle`),
  }
  return keys[award.windowState]?.() ?? ''
}

function awardRequestLine(award, text) {
  return text.t(`${ROOT}.award.request`, {
    number: isolate(award.openRequest.number),
    status: text.t(statusLabelKey(award.openRequest.status)),
  })
}

const AWARD_STATUS_LINES = {
  [W.LATE_PAYMENT]: (award, text) => text.t(`${ROOT}.award.windowClosedBeforePayment`),
  [W.AWAITING_PAYMENT]: (award, text) => awaitingLine(award.awaitingPayment, text),
  [W.UNAVAILABLE]: (award, text) => text.t(`${ROOT}.unavailable`),
  [W.DRAFT]: awardRequestLine,
  [W.SUBMITTED]: awardRequestLine,
  [W.ANNOUNCED]: (award, text) =>
    text.t(`${ROOT}.award.announced`, { percent: text.percent(award.lastResult.percent) }),
  [W.ELIGIBLE]: (award, text) => text.t(`${ROOT}.award.eligible`),
  [W.NOT_ELIGIBLE]: (award, text) =>
    text.t(`paths.award.reasons.${award.eligibility.reason ?? 'unknown'}`),
}

function awardSummary(award, text) {
  const state = awardStateOf(award)
  if (state === W.HIDDEN) return { key: PATH_KEYS.AWARD, state, lines: [], status: '' }
  const status = AWARD_STATUS_LINES[state]?.(award, text) ?? awardWindowLine(award, text)
  const window = awardWindowLine(award, text)
  return {
    key: PATH_KEYS.AWARD,
    state,
    status,
    lines: window && window !== status ? [window] : [],
    action: awardAction(state, award),
  }
}

export function usePathSummaries(pathsSource) {
  const text = useText()
  return computed(() => {
    const paths = toValue(pathsSource)
    if (!paths) return null
    return {
      [PATH_KEYS.SELF]: selfSummary(paths.self, text),
      [PATH_KEYS.ACCREDITED]: accreditedSummary(paths.accredited, text),
      [PATH_KEYS.AWARD]: awardSummary(paths.award, text),
    }
  })
}
