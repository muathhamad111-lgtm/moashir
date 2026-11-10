import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import NextStepCard from '@/components/portal/home/NextStepCard.vue'
import { createTestGlobals } from '@/test-utils'

const STEP = {
  rank: 7,
  key: 'apply_accredited',
  path: 'accredited',
  target: { route: 'paths.accredited', params: {}, query: {}, href: '/paths/accredited' },
  context: {
    assessmentId: null,
    number: null,
    status: null,
    paymentId: null,
    isPaid: null,
    deadlineAt: null,
    periodEndsOn: '2027-03-31',
    cycleId: null,
  },
}

function factory(step, locale = 'ar') {
  return mount(NextStepCard, { props: { step }, global: createTestGlobals({ locale }) })
}

describe('NextStepCard', () => {
  it('shows the rank-7 step with the isolated period end and its path link', () => {
    const wrapper = factory(STEP)
    expect(wrapper.attributes('data-rank')).toBe('7')
    expect(wrapper.find('.next-step__title').text()).toBe('اطلب التقييم المعتمد لهذه الفترة')
    expect(wrapper.find('.next-step__text bdi').text()).toBe('2027-03-31')
    expect(wrapper.find('.next-step__action--primary').attributes('href')).toBe('/paths/accredited')
  })

  it('renders the self steps in English with the path page link', () => {
    const wrapper = factory(
      {
        ...STEP,
        rank: 10,
        key: 'start_self',
        path: 'self',
        target: { route: 'paths.self', params: {}, query: {}, href: '/paths/self' },
        context: { ...STEP.context, periodEndsOn: null },
      },
      'en',
    )
    expect(wrapper.find('.next-step__title').text()).toBe('Start a self assessment (optional)')
    expect(wrapper.find('.next-step__action--primary').text()).toBe('Start the self assessment')
  })

  it('shows the rank-1 card channel, reference and order expiry for the award path', () => {
    const wrapper = factory({
      ...STEP,
      rank: 1,
      key: 'settle_payment',
      path: 'advanced',
      target: { route: 'paths.award', params: {}, query: {}, href: '/paths/award' },
      context: {
        ...STEP.context,
        periodEndsOn: null,
        paymentReference: 'PAY-2026-000121',
        paymentChannel: 'electronic',
        paymentStatus: 'pending',
        paymentExpiresAt: '2026-11-10T09:30:00+00:00',
      },
    })
    expect(wrapper.find('.next-step__text').text()).toContain('الجائزة')
    const payment = wrapper.find('[data-testid="next-step-payment"]')
    expect(payment.text()).toContain('PAY-2026-000121')
    expect(wrapper.find('[data-testid="next-step-expiry"]').text()).toContain('تنتهي صلاحية الطلب')
  })

  it('asks for a new order after a rejected transfer, without an expiry', () => {
    const wrapper = factory(
      {
        ...STEP,
        rank: 1,
        key: 'settle_payment',
        path: 'self',
        target: { route: 'paths.self', params: {}, query: {}, href: '/paths/self' },
        context: {
          ...STEP.context,
          periodEndsOn: null,
          paymentReference: 'PAY-2026-000130',
          paymentChannel: 'bank_transfer',
          paymentStatus: 'rejected',
          paymentExpiresAt: null,
        },
      },
      'en',
    )
    expect(wrapper.find('.next-step__title').text()).toBe('Create a new payment order')
    expect(wrapper.find('[data-testid="next-step-expiry"]').exists()).toBe(false)
  })
})
