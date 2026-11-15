import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CommitteePriorScores from '@/components/committee/CommitteePriorScores.vue'
import { createTestGlobals } from '@/test-utils'

describe('CommitteePriorScores', () => {
  it('names each prior score by its guiding point instead of its code', () => {
    const wrapper = mount(CommitteePriorScores, {
      props: {
        prior: {
          scores: { points: { 1001: { rawValue: '8', percent: '80.00' } } },
          notes: { subs: {} },
        },
        units: [
          { level: 'point', id: 1001, label: { ar: 'ميثاق معتمد', en: 'An approved charter' } },
          { level: 'point', id: 1002, label: { ar: 'تقرير سنوي', en: 'An annual report' } },
        ],
        subId: 101,
      },
      global: createTestGlobals({ locale: 'en' }),
    })
    const rows = wrapper.findAll('.committee-prior__score')
    expect(rows[0].text()).toContain('An approved charter')
    expect(rows[1].text()).toContain('An annual report')
    expect(wrapper.text()).not.toMatch(/M\d/)
  })
})
