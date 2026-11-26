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
        typeOf: () => 'score',
      },
      global: createTestGlobals({ locale: 'en' }),
    })
    const rows = wrapper.findAll('.committee-prior__score')
    expect(rows[0].text()).toContain('An approved charter')
    expect(rows[1].text()).toContain('An annual report')
    expect(wrapper.text()).not.toMatch(/M\d/)
  })

  it('shows a prior achievement judgment as achieved or not, and a score with its percent', () => {
    const wrapper = mount(CommitteePriorScores, {
      props: {
        prior: {
          scores: {
            subs: { 201: { rawValue: '1.00', percent: '100.00' } },
            points: { 1001: { rawValue: '8.00', percent: '80.00' } },
          },
          notes: { subs: {} },
        },
        units: [
          { level: 'sub', id: 201, label: { en: 'Policy' }, scoringType: 'achievement' },
          { level: 'point', id: 1001, label: { en: 'Charter' }, scoringType: 'score' },
        ],
        subId: 101,
        typeOf: (unit) => unit.scoringType,
      },
      global: createTestGlobals({ locale: 'en' }),
    })
    const rows = wrapper.findAll('.committee-prior__score').map((row) => row.text())
    expect(rows[0]).toContain('Achieved')
    expect(rows[0]).not.toContain('1.00')
    expect(rows[1]).toContain('8.00')
    expect(rows[1]).toContain('80.00')
  })
})
