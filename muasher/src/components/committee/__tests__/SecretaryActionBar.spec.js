import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import SecretaryActionBar from '../SecretaryActionBar.vue'

const NONE = { recompute: false, approve: false, returnToCommittee: false, discussion: false }

function mountBar(props) {
  const globals = createTestGlobals({ locale: 'en' })
  return mount(SecretaryActionBar, { props, global: { plugins: globals.plugins } })
}

describe('SecretaryActionBar', () => {
  it('renders nothing when no decision is possible', () => {
    expect(mountBar({ flags: NONE }).find('.award-action-bar').exists()).toBe(false)
  })

  it('offers only the decisions the API flags allow and reports them', async () => {
    const wrapper = mountBar({ flags: { ...NONE, approve: true, returnToCommittee: true } })
    const labels = wrapper.findAll('button').map((button) => button.text())
    expect(labels).toEqual(['Approve the average', 'Return to the committee'])
    await wrapper.findAll('button')[0].trigger('click')
    expect(wrapper.emitted('open')).toEqual([['approve']])
  })

  it('explains a pending substitute result even before any decision is possible', () => {
    const wrapper = mountBar({ flags: NONE, pendingSubstitutions: 1 })
    expect(wrapper.find('.cycle-actions__note').exists()).toBe(true)
    expect(wrapper.findAll('button')).toHaveLength(0)
  })
})
