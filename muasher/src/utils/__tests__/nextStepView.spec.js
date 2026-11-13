import { describe, expect, it } from 'vitest'
import { nextStepView } from '@/utils/nextStepView'

function stepOf(key, target, context = {}) {
  return {
    rank: 1,
    key,
    path: 'accredited',
    target: { params: {}, query: {}, href: null, ...target },
    context: { number: null, deadlineAt: null, periodEndsOn: null, ...context },
  }
}

describe('nextStepView (03 §19.6, server-ranked)', () => {
  it('links a known route name with string params and the query', () => {
    const view = nextStepView(
      stepOf('complete_request', {
        route: 'paths.accredited.request',
        params: { id: 42 },
        query: { tab: 'request' },
      }),
    )
    expect(view.to).toEqual({
      name: 'paths.accredited.request',
      params: { id: '42' },
      query: { tab: 'request' },
    })
    expect(view.titleKey).toBe('portal.home.next.steps.complete_request.title')
    expect(view.textKey).toBe('portal.home.next.steps.complete_request.text')
  })

  it('uses the dated text when the context carries a deadline or a period end', () => {
    const view = nextStepView(
      stepOf('apply_accredited', { route: 'paths.accredited' }, { periodEndsOn: '2027-03-31' }),
    )
    expect(view.textKey).toBe('portal.home.next.steps.apply_accredited.textDate')
    expect(view.date).toBe('2027-03-31')
  })

  it('falls back to an internal href for an unknown route and refuses an external one', () => {
    expect(nextStepView(stepOf('start_self', { route: 'other', href: '/paths/self' })).to).toBe(
      '/paths/self',
    )
    expect(
      nextStepView(stepOf('start_self', { route: 'other', href: 'https://elsewhere.example.sa' }))
        .to,
    ).toBeNull()
  })
})
