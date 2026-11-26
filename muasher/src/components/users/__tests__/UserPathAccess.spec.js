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
  it('reads the paths a user works on as one short line', () => {
    const textOf = (user, locale = 'en') =>
      mount(UserPathAccessCell, {
        props: { user },
        global: { plugins: createTestGlobals({ locale }).plugins },
      }).text()
    expect(textOf(ASSESSOR)).toBe('Accredited')
    expect(textOf(FOLLOWER)).toBe('Accredited')
    expect(textOf({ ...FOLLOWER, requestScope: { allPaths: true, paths: [] } })).toBe('All')
    expect(
      textOf({ ...FOLLOWER, requestScope: { allPaths: false, paths: ['advanced', 'accredited'] } }),
    ).toBe('Accredited, Advanced')
    expect(textOf({ ...ASSESSOR, assessorQualifications: [] })).toBe('Not set')
    expect(textOf({ ...FOLLOWER, requestScope: { allPaths: true, paths: [] } }, 'ar')).toBe('الكل')
    expect(textOf({ ...FOLLOWER, roles: ['system_admin'] })).toBe('Accredited')
    expect(textOf({ ...ASSESSOR, roles: ['finance_officer'] })).toBe('')
  })
})
