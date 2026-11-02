import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import AssignAssessorDialog from '../AssignAssessorDialog.vue'
import { createTestGlobals } from '@/test-utils'
import { requestsService } from '@/services/requestsService'

vi.mock('@/services/requestsService', () => ({
  requestsService: { listAssessors: vi.fn(), assign: vi.fn(), reassign: vi.fn() },
}))

const ASSESSORS = [
  { id: 11, name: 'أ. هند بنت إبراهيم الزهراني', openRequests: 1 },
  { id: 15, name: 'د. طلال بن مساعد الرشيدي', openRequests: 0 },
]

function mountDialog(props = {}) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  return mount(AssignAssessorDialog, {
    props: { modelValue: true, requestId: 9108, entityName: 'Ehsan Charitable Society', ...props },
    global: { plugins: globals.plugins },
    attachTo: document.body,
  })
}

beforeEach(() => {
  requestsService.listAssessors.mockResolvedValue({ data: ASSESSORS })
  requestsService.assign.mockResolvedValue({ data: { id: 9105, status: 'assigned' } })
  requestsService.reassign.mockResolvedValue({ data: { id: 9108, status: 'under-review' } })
})

afterEach(() => {
  document.body.innerHTML = ''
  vi.clearAllMocks()
})

describe('AssignAssessorDialog', () => {
  it('loads the eligible assessors of the request and assigns without a reason', async () => {
    const wrapper = mountDialog()
    await flushPromises()
    expect(requestsService.listAssessors).toHaveBeenCalledWith({ assessmentId: 9108 })
    const radios = document.querySelectorAll('.assign-dialog__radio')
    expect(radios).toHaveLength(2)
    radios[1].click()
    document.querySelector('.modal form').dispatchEvent(new Event('submit'))
    await flushPromises()
    expect(requestsService.assign).toHaveBeenCalledWith(9108, { assessorId: 15 })
    expect(wrapper.emitted('done')[0][0]).toMatchObject({ status: 'assigned' })
    wrapper.unmount()
  })

  it('requires a reason of at least 10 characters to reassign', async () => {
    const wrapper = mountDialog({ mode: 'reassign' })
    await flushPromises()
    document.querySelectorAll('.assign-dialog__radio')[0].click()
    document.querySelector('.modal form').dispatchEvent(new Event('submit'))
    await flushPromises()
    expect(requestsService.reassign).not.toHaveBeenCalled()
    expect(document.querySelector('.field__error')?.textContent).toBeTruthy()
    wrapper.unmount()
  })
})
