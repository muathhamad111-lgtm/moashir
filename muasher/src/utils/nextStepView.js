import { NEXT_STEP_PATH_KEYS, NEXT_STEP_ROUTES } from '@/constants/entityPaths'
import { PAYMENT_STATUSES } from '@/constants/payments'
import { isInternalPath } from '@/utils/internalPath'
import { isoDate } from '@/utils/datetime'

const ROOT = 'portal.home.next'
const DATE_FIELDS = ['deadlineAt', 'periodEndsOn']
const SETTLE_PAYMENT = 'settle_payment'

function targetOf(step) {
  const { route, params, query, href } = step.target
  if (NEXT_STEP_ROUTES.includes(route)) {
    const stringParams = Object.fromEntries(
      Object.entries(params ?? {}).map(([key, value]) => [key, String(value)]),
    )
    return { name: route, params: stringParams, query: query ?? {} }
  }
  return isInternalPath(href) ? href : null
}

function dateOf(context) {
  const field = DATE_FIELDS.find((key) => context[key])
  return field ? isoDate(context[field]) : ''
}

function stepKeyOf(step) {
  return step.key === SETTLE_PAYMENT && step.context.paymentStatus === PAYMENT_STATUSES.REJECTED
    ? `${step.key}_rejected`
    : step.key
}

function paymentOf(step) {
  const { paymentChannel, paymentReference, paymentExpiresAt } = step.context
  if (step.key !== SETTLE_PAYMENT || !paymentChannel) return null
  return { channel: paymentChannel, reference: paymentReference, expiresAt: paymentExpiresAt }
}

export function nextStepView(step) {
  const date = dateOf(step.context)
  const key = stepKeyOf(step)
  return {
    titleKey: `${ROOT}.steps.${key}.title`,
    textKey: `${ROOT}.steps.${key}.${date ? 'textDate' : 'text'}`,
    actionKey: `${ROOT}.steps.${key}.action`,
    date,
    pathKey: NEXT_STEP_PATH_KEYS[step.path] ?? step.path,
    payment: paymentOf(step),
    to: targetOf(step),
  }
}
