import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import AssessorQualificationsDialog from '@/components/users/AssessorQualificationsDialog.vue'
import RequestScopeDialog from '@/components/users/RequestScopeDialog.vue'
import UserPathAccessCell from '@/components/users/UserPathAccessCell.vue'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'
import { useAuthStore } from '@/stores/auth'
import { usersService } from '@/services/usersService'

vi.mock('@/services/usersService', () => ({
  usersService: { updateAssessorQualifications: vi.fn(), updateRequestScope: vi.fn() },
}))

const ASSESSOR = {
  id: 10,
  name: 'د. سلطان بن عايض القرني',
  roles: ['assessor'],
  assessorQualifications: ['accredited'],
  requestScope: { allPaths: true, paths: ['accredited', 'advanced'] },
}
const FOLLOWER = {
  id: 28,
  name: 'شيماء بنت ناصر البلوي',
  roles: ['request_follower'],
  assessorQualifications: [],
  requestScope: { allPaths: false, paths: ['accredited'] },
}

let wrapper

function mountWith(component, props, { superAdmin = false } = {}) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(
    sessionUserFor(['system_admin'], { id: 1, isSuperAdmin: superAdmin }),
  )
  wrapper = mount(component, {
    props,
    attachTo: document.body,
    global: globals,
  })
  return wrapper
}

async function settle() {
  for (let round = 0; round < 3; round += 1) {
    await new Promise((resolve) => setTimeout(resolve, 10))
    await flushPromises()
  }
}

async function submit() {
  await settle()
  document.querySelector('.modal form').dispatchEvent(new Event('submit'))
  await settle()
}

beforeEach(() => {
  usersService.updateAssessorQualifications.mockImplementation((id, payload) =>
    Promise.resolve({ data: { ...ASSESSOR, assessorQualifications: payload.qualifications } }),
  )
  usersService.updateRequestScope.mockResolvedValue({ data: FOLLOWER })
})

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
  vi.clearAllMocks()
})

describe('assessor qualifications dialog', () => {
  it('starts from the current qualifications and sends the new set', async () => {
    mountWith(AssessorQualificationsDialog, { modelValue: true, user: ASSESSOR })
    const boxes = document.querySelectorAll('.checkbox__input')
    expect(boxes).toHaveLength(2)
    expect(boxes[0].checked).toBe(true)
    boxes[1].click()
    await submit()
    expect(usersService.updateAssessorQualifications).toHaveBeenCalledWith(10, {
      qualifications: ['accredited', 'advanced_initial'],
    })
    expect(wrapper.emitted('saved')[0][0].assessorQualifications).toEqual([
      'accredited',
      'advanced_initial',
    ])
  })

  it('asks the Super Admin for a reason before saving', async () => {
    mountWith(
      AssessorQualificationsDialog,
      { modelValue: true, user: ASSESSOR },
      { superAdmin: true },
    )
    await submit()
    expect(usersService.updateAssessorQualifications).not.toHaveBeenCalled()
    expect(document.querySelector('.field__error')).not.toBeNull()
  })

  it('shows the server refusal on the field', async () => {
    const refusal = Object.assign(new Error('not_assessor'), {
      normalized: {
        code: 'assessor_qualification.not_assessor',
        message: 'Only assessors can be qualified.',
        fieldErrors: { qualifications: ['Only assessors can be qualified.'] },
      },
    })
    usersService.updateAssessorQualifications.mockImplementation(
      () => new Promise((resolve, reject) => setTimeout(() => reject(refusal), 15)),
    )
    mountWith(AssessorQualificationsDialog, { modelValue: true, user: ASSESSOR })
    await submit()
    expect(document.querySelector('.checkbox-group .field__error').textContent).toContain(
      'Only assessors can be qualified.',
    )
    expect(wrapper.emitted('saved')).toBeUndefined()
  })
})

describe('request scope dialog', () => {
  it('limits the follower to the selected paths and needs one path at least', async () => {
    mountWith(RequestScopeDialog, { modelValue: true, user: FOLLOWER })
    const boxes = document.querySelectorAll('.checkbox__input')
    expect([...boxes].map((box) => box.checked)).toEqual([true, false])
    boxes[0].click()
    await submit()
    expect(usersService.updateRequestScope).not.toHaveBeenCalled()
    document.querySelectorAll('.checkbox__input')[1].click()
    await submit()
    expect(usersService.updateRequestScope).toHaveBeenCalledWith(28, {
      allPaths: false,
      paths: ['advanced'],
    })
  })

  it('opens every path again', async () => {
    mountWith(RequestScopeDialog, { modelValue: true, user: FOLLOWER })
    document.querySelectorAll('.radio__input')[0].click()
    await flushPromises()
    expect(document.querySelectorAll('.checkbox__input')).toHaveLength(0)
    await submit()
    expect(usersService.updateRequestScope).toHaveBeenCalledWith(28, { allPaths: true })
  })
})

describe('user path access cell', () => {
  it('shows the qualified paths of an assessor and the scope of a follower', () => {
    const globals = createTestGlobals({ locale: 'en' })
    const assessor = mount(UserPathAccessCell, {
      props: { user: ASSESSOR },
      global: { plugins: globals.plugins },
    })
    expect(assessor.findAll('.path-badge').map((badge) => badge.text())).toEqual([
      'Accredited assessment',
    ])
    const follower = mount(UserPathAccessCell, {
      props: { user: FOLLOWER },
      global: { plugins: globals.plugins },
    })
    expect(follower.text()).toContain('Follows')
    expect(follower.find('.path-badge--accredited').exists()).toBe(true)
    const unqualified = mount(UserPathAccessCell, {
      props: { user: { ...ASSESSOR, assessorQualifications: [] } },
      global: { plugins: globals.plugins },
    })
    expect(unqualified.text()).toContain('Not qualified for any path')
  })
})
