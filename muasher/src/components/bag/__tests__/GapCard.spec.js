import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import GapCard from '@/components/bag/GapCard.vue'
import { createTestGlobals } from '@/test-utils'

function localized(content) {
  if (!content) return ''
  return typeof content === 'string' ? content : content.en || content.ar || ''
}

const baseGap = {
  id: 'gap-503',
  subName: { ar: 'التحول الرقمي', en: 'Digital transformation' },
  mainName: { ar: 'العمليات', en: 'Operations' },
  current: 150,
  target: 250,
  gap: 100,
  gapPercent: 40,
  open: true,
  unmetPoints: [],
  helperFiles: [],
}

function mountCard(gap) {
  return mount(GapCard, {
    props: { gap, localized, downloadable: true },
    global: createTestGlobals({ locale: 'en' }),
  })
}

describe('GapCard — enriched gap model', () => {
  it('renders a high-priority badge with the danger variant', () => {
    const wrapper = mountCard({ ...baseGap, priority: 'high' })
    const badge = wrapper.find('.badge--danger')
    expect(badge.exists()).toBe(true)
    expect(badge.text()).toContain('High')
  })

  it('maps medium priority to the warning variant', () => {
    const wrapper = mountCard({ ...baseGap, priority: 'medium' })
    expect(wrapper.find('.badge--warning').exists()).toBe(true)
  })

  it('renders the consultant note and recommended action when present', () => {
    const wrapper = mountCard({
      ...baseGap,
      priority: 'high',
      consultantNote: { ar: 'ملاحظة', en: 'Start with digital.' },
      recommendedAction: { ar: 'إجراء', en: 'Approve the plan.' },
    })
    expect(wrapper.find('.gap-card__note').exists()).toBe(true)
    expect(wrapper.text()).toContain('Start with digital.')
    expect(wrapper.find('.gap-card__action').exists()).toBe(true)
    expect(wrapper.text()).toContain('Approve the plan.')
  })

  it('omits the note/action/priority blocks when absent', () => {
    const wrapper = mountCard({ ...baseGap, priority: null, consultantNote: null, recommendedAction: null })
    expect(wrapper.find('.gap-card__note').exists()).toBe(false)
    expect(wrapper.find('.gap-card__action').exists()).toBe(false)
    expect(wrapper.find('.badge--danger').exists()).toBe(false)
  })
})
