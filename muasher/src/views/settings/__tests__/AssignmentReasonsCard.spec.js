import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AssignmentReasonsCard from '@/components/settings/general/AssignmentReasonsCard.vue'
import { assignmentReasonsService } from '@/services/assignmentReasonsService'
import {
  assignmentReasonsValues,
  changedAssignmentReasons,
} from '@/utils/settings/assignmentReasonsForm'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

// Admin review 2026-11-20 (R3): two switches decide whether assigning and reassigning need a reason.
vi.mock('@/services/assignmentReasonsService', () => ({
  assignmentReasonsService: { get: vi.fn(), update: vi.fn() },
}))

const RULES = { assignRequired: false, reassignRequired: true }

beforeEach(() => {
  vi.clearAllMocks()
  assignmentReasonsService.get.mockResolvedValue({ data: RULES })
  assignmentReasonsService.update.mockResolvedValue({
    data: { assignRequired: true, reassignRequired: true },
  })
})

describe('assignment reason form rules', () => {
  it('starts from the saved switches and sends both switches on any change', () => {
    expect(assignmentReasonsValues(RULES)).toEqual(RULES)
    expect(assignmentReasonsValues(null)).toEqual(RULES)
    expect(changedAssignmentReasons(RULES, RULES)).toEqual({})
    expect(changedAssignmentReasons(RULES, { ...RULES, assignRequired: true })).toEqual({
      assignRequired: true,
      reassignRequired: true,
    })
  })
})

describe('assignment reason card in the general settings', () => {
  it('shows both switches with the Super Admin note and saves a change', async () => {
    const globals = createTestGlobals()
    useAuthStore(globals.pinia).setUser(sessionUserFor(['system_admin']))
    const wrapper = mount(AssignmentReasonsCard, { global: globals, attachTo: document.body })
    await flushPromises()
    expect(wrapper.find('legend').text()).toBe('سبب الإسناد')
    expect(wrapper.text()).toContain('السبب إلزامي عند الإسناد')
    expect(wrapper.text()).toContain('السبب إلزامي عند إعادة الإسناد')
    expect(wrapper.text()).toContain('يكتب مدير النظام الرئيسي سببًا دائمًا')
    const toggles = wrapper.findAll('input[type="checkbox"]')
    expect(toggles.map((toggle) => toggle.element.checked)).toEqual([false, true])
    await toggles[0].setValue(true)
    await wrapper.find('form').trigger('submit')
    await vi.waitFor(() =>
      expect(assignmentReasonsService.update).toHaveBeenCalledWith({
        assignRequired: true,
        reassignRequired: true,
      }),
    )
    wrapper.unmount()
  })
})
