import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AccreditedRequestCard from '@/components/accredited/AccreditedRequestCard.vue'
import AccreditedResultSummary from '@/components/accredited/AccreditedResultSummary.vue'
import { createTestGlobals } from '@/test-utils'

const ASSESSMENT = {
  id: 9101,
  number: 'ACC-2026-000004',
  path: 'accredited',
  status: 'draft',
  updatedAt: null,
  periodLabel: { ar: 'تُحدَّد عند الإرسال', en: 'Set on submission' },
}

function card(assessment, locale = 'ar') {
  return mount(AccreditedRequestCard, {
    props: { assessment, progress: { complete: 17, total: 19 } },
    global: createTestGlobals({ locale }),
  })
}

describe('AccreditedRequestCard heading (J-5, H-6)', () => {
  it('names the path, the request number and the period, never a journey station', () => {
    const title = card(ASSESSMENT).find('.request-card__title').text()
    expect(title).toContain('التقييم المعتمد')
    expect(title).toContain('ACC-2026-000004')
    expect(title).toContain('تُحدَّد عند الإرسال')
    expect(title).not.toContain('المحطة')
  })

  it('uses the award name for an advanced request in English', () => {
    const title = card(
      { ...ASSESSMENT, path: 'advanced', number: 'ADV-2026-000031', periodLabel: null },
      'en',
    )
      .find('.request-card__title')
      .text()
    expect(title).toContain('Award')
    expect(title).not.toMatch(/Station/)
  })
})

describe('AccreditedResultSummary ring', () => {
  it('shrinks a five-character score so it fits the ring', () => {
    const mountWith = (percent) =>
      mount(AccreditedResultSummary, {
        props: { result: { percent, total: '1', maxTotal: '2', completedAt: null } },
        global: createTestGlobals({ locale: 'en' }),
      })
    expect(mountWith('74.54').find('.accredited-result-summary__value--long').exists()).toBe(true)
    expect(mountWith('82').find('.accredited-result-summary__value--long').exists()).toBe(false)
  })
})
