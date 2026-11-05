import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import NextStepCard from '@/components/portal/home/NextStepCard.vue'
import { NEXT_STEPS } from '@/composables/useEntityJourney'
import { servicePricesService } from '@/services/servicePricesService'
import { contentService } from '@/services/contentService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/servicePricesService', () => ({
  servicePricesService: { getServicePrices: vi.fn() },
}))
vi.mock('@/services/contentService', () => ({ contentService: { getPathGuide: vi.fn() } }))

async function factory(step, locale = 'ar') {
  const wrapper = mount(NextStepCard, {
    props: { step },
    global: createTestGlobals({ locale }),
    attachTo: document.body,
  })
  await flushPromises()
  return wrapper
}

describe('NextStepCard', () => {
  it('counts down an open payment order and links its completion screen', async () => {
    const expiresAt = new Date(Date.now() + 18 * 60 * 1000).toISOString()
    const wrapper = await factory({
      kind: NEXT_STEPS.PAYMENT,
      paymentId: '3f0c9a52-6d1e-4b7a-9c21-5a1e00000021',
      reference: 'PAY-2026-000021',
      expiresAt,
    })
    expect(wrapper.find('.next-step__text bdi').text()).toBe('PAY-2026-000021')
    expect(wrapper.find('.next-step__countdown').text()).toContain('18 دقيقة')
    expect(wrapper.find('.next-step__action--primary').attributes('href')).toBe(
      '/payments/3f0c9a52-6d1e-4b7a-9c21-5a1e00000021/complete',
    )
    wrapper.unmount()
  })

  it('shows the completion deadline', async () => {
    const wrapper = await factory({
      kind: NEXT_STEPS.COMPLETION,
      assessmentId: 14,
      reference: 'ACC-2026-000014',
      dueAt: '2026-11-10T20:59:00Z',
      progress: null,
    })
    expect(wrapper.find('.next-step__due').text()).toContain('2026-11-10')
    expect(wrapper.find('.next-step__text').text()).toContain('ACC-2026-000014')
    wrapper.unmount()
  })

  it('adds the accredited price before VAT when the path is suggested', async () => {
    servicePricesService.getServicePrices.mockResolvedValue({
      data: { accredited: { priceBeforeVat: '1000.00' } },
    })
    const wrapper = await factory({ kind: NEXT_STEPS.ACCREDITED_START, restart: false }, 'en')
    expect(wrapper.find('.next-step__price').text()).toMatch(
      /^Fee 1,?000(\.00)? SAR — excluding VAT$/,
    )
    wrapper.unmount()
  })

  it('opens the station guide from the path settings', async () => {
    contentService.getPathGuide.mockResolvedValue({
      data: {
        entityTexts: {
          intro: { ar: 'مقدمة', en: 'Intro' },
          instructions: { ar: 'الخطوة الأولى\nالخطوة الثانية', en: 'One\nTwo' },
          afterSubmission: null,
        },
      },
    })
    const wrapper = await factory({
      kind: NEXT_STEPS.EVIDENCE,
      assessmentId: 14,
      reference: 'ACC-2026-000014',
      progress: { subsTotal: 2, subsComplete: 2, remaining: [] },
    })
    expect(wrapper.find('.next-step__text').text()).toContain('اكتملت شواهد جميع المعايير')
    await wrapper.find('.next-step__action--ghost').trigger('click')
    await flushPromises()
    expect(contentService.getPathGuide).toHaveBeenCalledWith('accredited')
    const steps = document.body.querySelectorAll('.station-guide__step')
    expect([...steps].map((step) => step.textContent)).toEqual(['الخطوة الأولى', 'الخطوة الثانية'])
    wrapper.unmount()
  })
})
