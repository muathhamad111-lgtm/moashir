import { ROUTE_NAMES as R } from '@/constants/routeNames'
import { REQUEST_TABS } from '@/constants/entityPaths'
import {
  ACCREDITED_STATES as A,
  AWARD_STATES as W,
  SELF_STATES as S,
  isPaidPath,
} from './pathStates'

const BANK_TRANSFER = 'bank_transfer'

export const PATH_RUNS = Object.freeze({ START_ACCREDITED: 'startAccredited' })

function action(labelKey, to, icon = 'arrow-right') {
  return { labelKey: `paths.actions.${labelKey}`, to, icon }
}

function runAction(labelKey, run, icon = 'arrow-right') {
  return { labelKey: `paths.actions.${labelKey}`, run, icon }
}

function requestTab(id, tab) {
  return { name: R.PATHS_ACCREDITED_REQUEST, params: { id: String(id) }, query: { tab } }
}

function formRoute(id) {
  return { name: R.ASSESSMENT_ACCREDITED_FORM, params: { id: String(id) } }
}

function paymentRoute(payment) {
  const name = payment.channel === BANK_TRANSFER ? R.PAYMENTS_DETAIL : R.PAYMENTS_COMPLETE
  return payment.paymentId ? { name, params: { id: payment.paymentId } } : { name: R.PAYMENTS_MINE }
}

const SELF_ACTIONS = {
  [S.DRAFT]: () => action('continueSelf', { name: R.ASSESSMENT_SELF }),
  [S.AWAITING_PAYMENT]: (path) => action('viewPayment', paymentRoute(path.awaitingPayment)),
  [S.PAY_START]: () => action('payAndStart', { name: R.ASSESSMENT_SELF_PAY }, 'credit-card'),
  [S.START]: () => action('startSelf', { name: R.ASSESSMENT_SELF }),
}

function applyAccredited(path) {
  return isPaidPath(path)
    ? action('applyAccredited', { name: R.ASSESSMENT_ACCREDITED_PAY })
    : runAction('startAccredited', PATH_RUNS.START_ACCREDITED)
}

const ACCREDITED_ACTIONS = {
  [A.AWAITING_PAYMENT]: (path) => action('viewPayment', paymentRoute(path.awaitingPayment)),
  [A.NEEDS_COMPLETION]: (path) => action('complete', formRoute(path.openRequest.id)),
  [A.DRAFT]: (path) => action('continueRequest', formRoute(path.openRequest.id)),
  [A.IN_REVIEW]: (path) =>
    action('viewRequest', requestTab(path.openRequest.id, REQUEST_TABS.REQUEST)),
  [A.ASSESSED]: (path) =>
    path.lastResult
      ? action('viewResult', requestTab(path.lastResult.assessmentId, REQUEST_TABS.RESULT))
      : null,
  [A.APPLY]: applyAccredited,
}

const AWARD_ACTIONS = {
  [W.AWAITING_PAYMENT]: (path) => action('viewPayment', paymentRoute(path.awaitingPayment)),
  [W.ELIGIBLE]: () => action('applyAward', { name: R.ASSESSMENT_ADVANCED }),
  [W.DRAFT]: (path) =>
    action('continueAward', {
      name: R.ASSESSMENT_ADVANCED_FORM,
      params: { id: String(path.openRequest.id) },
    }),
  [W.ANNOUNCED]: (path) =>
    action('viewAwardResult', {
      name: R.ASSESSMENT_ADVANCED_RESULT,
      params: { id: String(path.lastResult.assessmentId) },
    }),
}

export function selfAction(state, path) {
  return SELF_ACTIONS[state]?.(path) ?? null
}

export function accreditedAction(state, path) {
  return ACCREDITED_ACTIONS[state]?.(path) ?? null
}

export function awardAction(state, path) {
  return AWARD_ACTIONS[state]?.(path) ?? null
}

export function requestTabRoute(assessmentId, tab = REQUEST_TABS.REQUEST) {
  return requestTab(assessmentId, tab)
}

const SELF_DRAFT = 'draft'

const ROW_ACTIONS = {
  self: (row) => {
    if (row.result) return { name: R.ASSESSMENT_SELF_RESULT, params: { id: String(row.id) } }
    return row.isOpen && row.status === SELF_DRAFT ? { name: R.ASSESSMENT_SELF } : null
  },
  accredited: (row) => ({ name: R.PATHS_ACCREDITED_REQUEST, params: { id: String(row.id) } }),
  award: (row) => {
    if (row.result) return { name: R.ASSESSMENT_ADVANCED_RESULT, params: { id: String(row.id) } }
    return row.isOpen && row.status === SELF_DRAFT
      ? { name: R.ASSESSMENT_ADVANCED_FORM, params: { id: String(row.id) } }
      : null
  },
}

export function rowAction(path, row) {
  if (!row.id || row.isFrozen) return null
  return ROW_ACTIONS[path]?.(row) ?? null
}
