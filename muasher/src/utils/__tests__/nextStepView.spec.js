import { describe, expect, it } from 'vitest'
import { nextStepView } from '@/utils/nextStepView'
import { NEXT_STEPS } from '@/composables/useEntityJourney'
import { ROUTE_NAMES as R } from '@/constants/routeNames'

describe('next step view', () => {
  it('opens the accredited form and offers the station guide while collecting evidence', () => {
    const view = nextStepView({ kind: NEXT_STEPS.EVIDENCE, assessmentId: 14, progress: {} })
    expect(view.titleKey).toBe('portal.home.next.evidence.default.title')
    expect(view.textKey).toBe('portal.home.next.evidence.default.text')
    expect(view.primary.to).toEqual({ name: R.ASSESSMENT_ACCREDITED_FORM, params: { id: '14' } })
    expect(view.showsGuide).toBe(true)
  })

  it('uses the plain text when the progress is unknown', () => {
    const view = nextStepView({ kind: NEXT_STEPS.COMPLETION, assessmentId: 14, progress: null })
    expect(view.textKey).toBe('portal.home.next.completion.default.plain')
  })

  it('completes an open payment order on its task screen', () => {
    const view = nextStepView({ kind: NEXT_STEPS.PAYMENT, paymentId: 'order-1' })
    expect(view.primary.to).toEqual({ name: R.PAYMENTS_COMPLETE, params: { id: 'order-1' } })
    expect(view.secondary.to).toEqual({ name: R.PAYMENTS_MINE })
  })

  it('falls back to the checkout when the order id is unknown', () => {
    const view = nextStepView({ kind: NEXT_STEPS.PAYMENT })
    expect(view.primary.to).toEqual({ name: R.ASSESSMENT_ACCREDITED_PAY })
  })

  it('has a restart variant for the accredited start', () => {
    expect(nextStepView({ kind: NEXT_STEPS.ACCREDITED_START, restart: true }).titleKey).toBe(
      'portal.home.next.accreditedStart.restart.title',
    )
  })

  it('links the certificate and the feedback report after completion', () => {
    const view = nextStepView({ kind: NEXT_STEPS.CERTIFICATE, assessmentId: 9 })
    expect(view.primary.to).toEqual({ name: R.CERTIFICATES_INDEX })
    expect(view.secondary.to).toEqual({
      name: R.ASSESSMENT_ACCREDITED_FEEDBACK,
      params: { id: '9' },
    })
  })

  it('defaults to starting the self assessment', () => {
    expect(nextStepView(null).primary.to).toEqual({ name: R.ASSESSMENT_SELF })
  })
})
