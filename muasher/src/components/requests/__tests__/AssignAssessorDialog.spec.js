import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import AssignAssessorDialog from '../AssignAssessorDialog.vue'
import AppCombobox from '@/components/form/AppCombobox.vue'
import { createTestGlobals } from '@/test-utils'
import { requestsService } from '@/services/requestsService'

vi.mock('@/services/requestsService', () => ({
  requestsService: { listAssessors: vi.fn(), assign: vi.fn(), reassign: vi.fn() },
}))

const ASSESSORS = [
  { id: 11, name: 'أ. هند بنت إبراهيم الزهراني', openRequests: 1 },
  { id: 15, name: 'د. طلال بن مساعد الرشيدي', openRequests: 0 },
]

async function choose(wrapper, id) {
  wrapper.findComponent(AppCombobox).vm.$emit('update:modelValue', id)
  await flushPromises()
}

function submitForm() {
  document.querySelector('.modal form').dispatchEvent(new Event('submit'))
  return flushPromises()
}

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
  it('offers the eligible assessors in one searchable select with their open requests', async () => {
    const wrapper = mountDialog()
    await flushPromises()
    expect(requestsService.listAssessors).toHaveBeenCalledWith({ assessmentId: 9108 })
    const select = wrapper.findComponent(AppCombobox)
    expect(select.props('remote')).toBe(true)
    expect(select.props('options')).toEqual([
      { value: 11, label: 'أ. هند بنت إبراهيم الزهراني', description: '1 open request' },
      { value: 15, label: 'د. طلال بن مساعد الرشيدي', description: 'No open requests' },
    ])
    expect(document.querySelectorAll('.assign-dialog input[type="radio"]')).toHaveLength(0)
    wrapper.unmount()
  })

  it('assigns without a reason when the settings keep it optional', async () => {
    const wrapper = mountDialog()
    await flushPromises()
    await choose(wrapper, 15)
    await submitForm()
    expect(requestsService.assign).toHaveBeenCalledWith(9108, { assessorId: 15 })
    expect(wrapper.emitted('done')[0][0]).toMatchObject({ status: 'assigned' })
    wrapper.unmount()
  })

  it('requires a reason of at least 10 characters to reassign by default', async () => {
    const wrapper = mountDialog({ mode: 'reassign' })
    await flushPromises()
    await choose(wrapper, 11)
    await submitForm()
    expect(requestsService.reassign).not.toHaveBeenCalled()
    expect(document.querySelector('.field__error')?.textContent).toBeTruthy()
    wrapper.unmount()
  })

  it('follows the general settings: a required assign reason and an optional reassign one', async () => {
    requestsService.listAssessors.mockResolvedValue({
      data: ASSESSORS,
      meta: { reasonRules: { assignRequired: true, reassignRequired: false } },
    })
    const assign = mountDialog()
    await flushPromises()
    await choose(assign, 15)
    await submitForm()
    expect(requestsService.assign).not.toHaveBeenCalled()
    expect(document.querySelector('textarea').required).toBe(true)
    assign.unmount()
    document.body.innerHTML = ''

    const reassign = mountDialog({ mode: 'reassign' })
    await flushPromises()
    await choose(reassign, 11)
    await submitForm()
    expect(requestsService.reassign).toHaveBeenCalledWith(9108, { assessorId: 11 })
    reassign.unmount()
  })

  it('asks for a choice before submitting', async () => {
    const wrapper = mountDialog()
    await flushPromises()
    await submitForm()
    expect(requestsService.assign).not.toHaveBeenCalled()
    expect(wrapper.findComponent(AppCombobox).props('error')).toBeTruthy()
    wrapper.unmount()
  })
})

describe('AssignAssessorDialog path rules', () => {
  it('shows the path of the request and refreshes the list when the assessor is not qualified', async () => {
    requestsService.assign.mockRejectedValue({
      normalized: {
        status: 422,
        code: 'assessor.not_qualified',
        message: 'This assessor is not qualified for the path of this request.',
        fieldErrors: {
          assessorId: ['This assessor is not qualified for the path of this request.'],
        },
        meta: { requiredQualification: 'accredited', path: 'accredited' },
      },
    })
    const wrapper = mountDialog({ path: 'accredited' })
    await flushPromises()
    expect(document.querySelector('.assign-dialog__path .path-badge--accredited')).not.toBeNull()
    await choose(wrapper, 11)
    await submitForm()
    expect(wrapper.findComponent(AppCombobox).props('error')).toContain(
      'not qualified for the path',
    )
    expect(requestsService.listAssessors).toHaveBeenCalledTimes(2)
    expect(wrapper.emitted('done')).toBeUndefined()
    wrapper.unmount()
  })

  it('explains a request outside the followed paths', async () => {
    requestsService.listAssessors.mockRejectedValue({
      normalized: { status: 422, fieldErrors: { assessmentId: ['invalid'] } },
    })
    const wrapper = mountDialog()
    await flushPromises()
    expect(wrapper.findComponent(AppCombobox).props('hint')).toContain(
      'no longer in the paths you follow',
    )
    wrapper.unmount()
  })

  it('closes when the request left the scope while assigning', async () => {
    requestsService.assign.mockRejectedValue({ normalized: { status: 404, code: 'not_found' } })
    const wrapper = mountDialog()
    await flushPromises()
    await choose(wrapper, 11)
    await submitForm()
    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([false])
    wrapper.unmount()
  })
})
