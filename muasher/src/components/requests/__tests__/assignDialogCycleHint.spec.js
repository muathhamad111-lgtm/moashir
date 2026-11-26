import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import AssignAssessorDialog from '../AssignAssessorDialog.vue'
import AppCombobox from '@/components/form/AppCombobox.vue'
import { createTestGlobals } from '@/test-utils'
import { requestsService } from '@/services/requestsService'

vi.mock('@/services/requestsService', () => ({
  requestsService: { listAssessors: vi.fn(), assign: vi.fn(), reassign: vi.fn() },
}))

function mountDialog(props = {}) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  return mount(AssignAssessorDialog, {
    props: { modelValue: true, requestId: 9203, path: 'advanced', ...props },
    global: { plugins: globals.plugins },
    attachTo: document.body,
  })
}

afterEach(() => {
  document.body.innerHTML = ''
  vi.clearAllMocks()
})

describe('AssignAssessorDialog — award cycle without initial assessors', () => {
  it('explains that the cycle links no initial assessor and links its managers to the cycle', async () => {
    requestsService.listAssessors.mockResolvedValue({
      data: [],
      meta: { awardCycle: { hasInitialAssessors: false, cycleId: 31 } },
    })
    const wrapper = mountDialog()
    await flushPromises()
    expect(wrapper.findComponent(AppCombobox).props('hint')).toContain(
      'No initial assessor is linked to this award cycle yet',
    )
    const link = document.querySelector('.assign-dialog__cycle-link a')
    expect(link.getAttribute('href')).toBe('/admin/award/cycles/31')
    expect(document.querySelector('.assign-dialog__path').textContent).toContain(
      'initial assessors linked to the award cycle',
    )
  })

  it('gives a follower the explanation without the link', async () => {
    requestsService.listAssessors.mockResolvedValue({
      data: [],
      meta: { awardCycle: { hasInitialAssessors: false, cycleId: null } },
    })
    const wrapper = mountDialog()
    await flushPromises()
    expect(wrapper.findComponent(AppCombobox).props('hint')).toContain('No initial assessor')
    expect(document.querySelector('.assign-dialog__cycle-link')).toBeNull()
  })

  it('tells an empty search apart from a list with no eligible assessor', async () => {
    requestsService.listAssessors.mockResolvedValue({
      data: [],
      meta: { awardCycle: { hasInitialAssessors: true, cycleId: null } },
    })
    const wrapper = mountDialog()
    await flushPromises()
    expect(wrapper.findComponent(AppCombobox).props('hint')).toBe(
      'No qualified assessor is available for this request.',
    )
    wrapper.findComponent(AppCombobox).vm.$emit('search', 'zzz')
    await new Promise((resolve) => setTimeout(resolve, 400))
    await flushPromises()
    expect(wrapper.findComponent(AppCombobox).props('hint')).toBe(
      'No available assessor matches the search.',
    )
  })
})
