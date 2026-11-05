import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import EmailChangeDialog from '@/components/entities/profile/EmailChangeDialog.vue'
import PermanentDeleteDialog from '@/components/entities/admin/PermanentDeleteDialog.vue'
import { entitiesService } from '@/services/entitiesService'
import { entityLifecycleService } from '@/services/entityLifecycleService'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/entitiesService', () => ({
  entitiesService: {
    requestEmailChange: vi.fn(),
    confirmEmailChange: vi.fn(),
    updateMine: vi.fn(),
  },
}))
vi.mock('@/services/entityLifecycleService', () => ({
  entityLifecycleService: { permanentlyDelete: vi.fn() },
}))

const OTP = {
  challengeId: 'c-1',
  channel: 'email',
  length: 6,
  expiresAt: '2099-01-01T00:00:00Z',
  resendAvailableAt: '2099-01-01T00:00:00Z',
}
async function settle() {
  for (let round = 0; round < 3; round += 1) {
    await new Promise((resolve) => setTimeout(resolve, 10))
    await flushPromises()
  }
}

function mountWith(component, props) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore().setUser(sessionUserFor(['entity']))
  return mount(component, {
    props,
    global: { ...globals, stubs: { teleport: true } },
    attachTo: document.body,
  })
}

beforeEach(() => vi.clearAllMocks())

describe('EmailChangeDialog', () => {
  it('sends the code to the new address, verifies it and shows the awaiting-approval outcome', async () => {
    entitiesService.requestEmailChange.mockResolvedValue({
      data: { otp: OTP, message: 'Code sent' },
    })
    entitiesService.confirmEmailChange
      .mockRejectedValueOnce(
        Object.assign(new Error('invalid'), {
          normalized: { fieldErrors: { code: ['The verification code is incorrect.'] } },
        }),
      )
      .mockResolvedValueOnce({
        data: {
          status: 'awaiting-approval',
          policy: 'requires_approval',
          newEmail: 'o***@albirr.example.sa',
        },
      })
    const wrapper = mountWith(EmailChangeDialog, { modelValue: true })
    await wrapper.find('[name="newEmail"]').setValue('office@albirr.example.sa')
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(entitiesService.requestEmailChange).toHaveBeenCalledWith('office@albirr.example.sa')
    expect(wrapper.text()).toContain('office@albirr.example.sa')

    wrapper.vm.$.setupState.verify('111111')
    await settle()
    expect(wrapper.find('.email-change__error').text()).toBe('The verification code is incorrect.')
    wrapper.vm.$.setupState.verify('482913')
    await settle()
    expect(entitiesService.confirmEmailChange).toHaveBeenLastCalledWith({
      challengeId: 'c-1',
      code: '482913',
    })
    expect(wrapper.find('.email-change--done').text()).toContain('o***@albirr.example.sa')
    expect(wrapper.emitted('completed')).toHaveLength(1)
    wrapper.unmount()
  })
})

describe('PermanentDeleteDialog', () => {
  const body = (selector) => document.body.querySelector(selector)

  function type(selector, value) {
    const element = body(selector)
    element.value = value
    element.dispatchEvent(new Event('input'))
  }

  it('sends the typed reference, the disclaimer and the reason and keeps a server mismatch on the field', async () => {
    entityLifecycleService.permanentlyDelete
      .mockRejectedValueOnce(
        Object.assign(new Error('mismatch'), {
          normalized: {
            code: 'confirmation.mismatch',
            message: 'Mismatch',
            fieldErrors: {
              confirmation: ['The typed confirmation does not match the entity reference.'],
            },
          },
        }),
      )
      .mockResolvedValueOnce({
        data: { reference: 'ENT-007003', pseudonym: 'ERASED-007003', kept: [] },
      })
    const globals = createTestGlobals({ locale: 'en' })
    setActivePinia(globals.pinia)
    const entity = {
      id: 7003,
      reference: 'ENT-007003',
      name: { ar: 'وقف النور التعليمي', en: 'Al-Noor Educational Waqf' },
    }
    const wrapper = mount(PermanentDeleteDialog, {
      props: { modelValue: false, entity },
      global: globals,
      attachTo: document.body,
    })
    await wrapper.setProps({ modelValue: true })
    expect(document.body.querySelectorAll('.permanent-delete__list li')).toHaveLength(6)
    const disclaimer = body('[name="disclaimerAcknowledged"]')
    disclaimer.checked = true
    disclaimer.dispatchEvent(new Event('change'))
    type('[name="confirmation"]', 'ent-007003')
    type('[name="reason"]', 'Approved erasure request of the entity')
    await settle()
    body('.permanent-delete').dispatchEvent(new Event('submit'))
    await settle()
    expect(entityLifecycleService.permanentlyDelete).toHaveBeenCalledWith(7003, {
      reason: 'Approved erasure request of the entity',
      confirmation: 'ent-007003',
      disclaimerAcknowledged: true,
    })
    expect(body('.permanent-delete').textContent).toContain(
      'The typed confirmation does not match the entity reference.',
    )
    body('.permanent-delete').dispatchEvent(new Event('submit'))
    await settle()
    expect(wrapper.emitted('erased')[0][0]).toMatchObject({ pseudonym: 'ERASED-007003' })
    wrapper.unmount()
  })
})
