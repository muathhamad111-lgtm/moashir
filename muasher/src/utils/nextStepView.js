import { NEXT_STEPS as N } from '@/composables/useEntityJourney'
import { ROUTE_NAMES as R } from '@/constants/routeNames'

const ROOT = 'portal.home.next'

function formRoute(step) {
  return { name: R.ASSESSMENT_ACCREDITED_FORM, params: { id: String(step.assessmentId) } }
}

function action(labelKey, to, icon = 'arrow-right') {
  return { labelKey: `${ROOT}.actions.${labelKey}`, to, icon }
}

function paymentRoute(step) {
  return step.paymentId
    ? { name: R.PAYMENTS_COMPLETE, params: { id: step.paymentId } }
    : { name: R.ASSESSMENT_ACCREDITED_PAY }
}

const HISTORY = { name: R.ASSESSMENT_HISTORY }
const SELF = { name: R.ASSESSMENT_SELF }

const VIEWS = {
  [N.PAYMENT]: (step) => ({
    primary: action('pay', paymentRoute(step)),
    secondary: action('payments', { name: R.PAYMENTS_MINE }, 'credit-card'),
  }),
  [N.COMPLETION]: (step) => ({ primary: action('complete', formRoute(step)), secondary: null }),
  [N.EVIDENCE]: (step) => ({ primary: action('evidence', formRoute(step)), secondary: null }),
  [N.IN_REVIEW]: () => ({
    primary: action('assessments', HISTORY, 'clipboard-check'),
    secondary: null,
  }),
  [N.SELF_DRAFT]: () => ({ primary: action('selfContinue', SELF), secondary: null }),
  [N.SELF_SUBMITTED]: () => ({
    primary: action('assessments', HISTORY, 'clipboard-check'),
    secondary: null,
  }),
  [N.SELF_START]: () => ({ primary: action('selfStart', SELF), secondary: null }),
  [N.ACCREDITED_START]: () => ({
    primary: action('accreditedStart', { name: R.ASSESSMENT_ACCREDITED }),
    secondary: null,
  }),
  [N.CERTIFICATE]: (step) => ({
    primary: action('certificates', { name: R.CERTIFICATES_INDEX }, 'award'),
    secondary: action(
      'feedback',
      { name: R.ASSESSMENT_ACCREDITED_FEEDBACK, params: { id: String(step.assessmentId) } },
      'doc',
    ),
  }),
}

const PROGRESS_KINDS = [N.EVIDENCE, N.COMPLETION]

function variantOf(step) {
  return step.kind === N.ACCREDITED_START && step.restart ? 'restart' : 'default'
}

function textNameOf(step) {
  return PROGRESS_KINDS.includes(step.kind) && !step.progress ? 'plain' : 'text'
}

export function nextStepView(source) {
  const step = source ?? { kind: N.SELF_START }
  const variant = variantOf(step)
  return {
    kind: step.kind,
    titleKey: `${ROOT}.${step.kind}.${variant}.title`,
    textKey: `${ROOT}.${step.kind}.${variant}.${textNameOf(step)}`,
    showsGuide: PROGRESS_KINDS.includes(step.kind),
    ...VIEWS[step.kind](step),
  }
}
