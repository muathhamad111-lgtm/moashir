import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CommitteeAssessorDetailPanel from '@/components/committee/CommitteeAssessorDetailPanel.vue'
import { createTestGlobals } from '@/test-utils'

const DETAIL = { tree: [], scores: { subs: {}, points: {} }, notes: [], total: 72 }

afterEach(() => {
  vi.restoreAllMocks()
})

describe('CommitteeAssessorDetailPanel', () => {
  it('renders an initial assessment detail without a committee status and without a Vue warning', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
    const wrapper = mount(CommitteeAssessorDetailPanel, {
      props: { detail: DETAIL, localized: (value) => value?.en ?? '' },
      global: { plugins: createTestGlobals({ locale: 'en' }).plugins },
    })
    expect(wrapper.find('.badge').exists()).toBe(false)
    expect(wrapper.find('.committee-assessor-detail__total').exists()).toBe(true)
    expect(warn.mock.calls.flat().join(' ')).not.toContain('[Vue warn]')
  })

  it('shows the committee assessor status when the detail carries it', () => {
    const wrapper = mount(CommitteeAssessorDetailPanel, {
      props: {
        detail: { ...DETAIL, committeeAssessorStatus: 'completed' },
        localized: (value) => value?.en ?? '',
      },
      global: { plugins: createTestGlobals({ locale: 'en' }).plugins },
    })
    expect(wrapper.find('.badge').text()).toBe('Completed')
  })
})
