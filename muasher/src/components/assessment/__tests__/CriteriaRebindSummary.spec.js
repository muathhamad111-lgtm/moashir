import { beforeEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CriteriaRebindSummary from '@/components/assessment/self/CriteriaRebindSummary.vue'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'

const UPDATE = {
  fromVersionId: 3,
  fromVersionNumber: 5,
  toVersionId: 7,
  toVersionNumber: 6,
  movedAt: '2026-11-20T09:00:00Z',
  scope: 'all',
  trigger: 'criteria_edit',
  kept: [],
  reset: [
    { level: 'point', code: 'M1.1.1', label: { en: 'Vision' } },
    { level: 'point', code: 'M1.1.2', label: { en: 'Goals' } },
  ],
  added: [],
}

function mountAs(permissions, props = {}) {
  const globals = createTestGlobals({ locale: 'en' })
  const wrapper = mount(CriteriaRebindSummary, {
    props: { update: UPDATE, assessmentId: 9107, context: 'review', ...props },
    global: { ...globals, stubs: { teleport: true } },
  })
  useAuthStore(globals.pinia).user = { id: 77, permissions }
  return wrapper
}

describe('CriteriaRebindSummary (compact alert)', () => {
  beforeEach(() => localStorage.clear())

  it('fits the move in one line and links staff to the version changes', async () => {
    const wrapper = mountAs(['criteria.view'])
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.assessment-notice__title').text()).toBe(
      'This request moved from criteria version 5 to version 6',
    )
    expect(wrapper.find('.assessment-notice__message').text()).toBe('2 items need a new score')
    expect(wrapper.find('a[href="/admin/settings/criteria/versions/7"]').exists()).toBe(true)
  })

  it('hides the line for this viewer and keeps the counts in a chip that brings it back', async () => {
    const wrapper = mountAs([])
    await wrapper.vm.$nextTick()
    expect(wrapper.find('a[href^="/admin/settings/criteria"]').exists()).toBe(false)
    await wrapper.find('[aria-label="Hide the alert"]').trigger('click')
    const chip = wrapper.find('.rebind-chip')
    expect(chip.text()).toBe('Criteria: version 6 · 2 need a new score')
    await chip.trigger('click')
    expect(wrapper.find('.rebind-notice').exists()).toBe(true)
  })
})
