import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import NationalIdentityCard from '@/components/account/NationalIdentityCard.vue'
import { identityService } from '@/services/identityService'
import { useAuthStore } from '@/stores/auth'
import { useAccountCompletionStore } from '@/stores/accountCompletion'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/identityService', () => ({
  identityService: { myChanges: vi.fn(), submitChange: vi.fn(), cancelChange: vi.fn() },
}))

const FIRST_ENTRY = {
  id: 'c0',
  status: 'pending',
  kind: 'first_entry',
  fields: ['national_id'],
  requestedName: null,
  requestedNationalIdMasked: '••••••0008',
  reason: null,
  reviewNote: null,
  createdAt: '2026-11-21T10:00:00Z',
  decidedAt: null,
}

const PENDING = {
  id: 'c1',
  status: 'pending',
  fields: ['name'],
  requestedName: 'سلمان المطيري',
  requestedNationalIdMasked: null,
  reason: 'تصحيح خطأ إملائي في الاسم',
  reviewNote: null,
  createdAt: '2026-11-20T10:00:00Z',
  decidedAt: null,
}

async function mountCard(
  user,
  changes = [],
  locale = 'ar',
  { roles = ['assessor'], props = {} } = {},
) {
  identityService.myChanges.mockResolvedValue({ data: changes })
  const globals = createTestGlobals({ locale })
  useAuthStore(globals.pinia).setUser(sessionUserFor(roles, user))
  const wrapper = mount(NationalIdentityCard, { props, global: globals, attachTo: document.body })
  await flushPromises()
  return { wrapper, globals }
}

beforeEach(() => vi.clearAllMocks())

describe('national identity card', () => {
  it('shows only the masked number and offers a change request', async () => {
    const { wrapper } = await mountCard(
      { hasNationalId: true, nationalIdMasked: '••••••0008' },
      [],
      'en',
    )
    expect(wrapper.find('[data-testid="national-id-masked"]').text()).toBe('••••••0008')
    expect(wrapper.text()).toContain('Request a change')
    wrapper.unmount()
  })

  it('offers to add the number when none is recorded', async () => {
    const { wrapper } = await mountCard({ hasNationalId: false }, [], 'en')
    expect(wrapper.text()).toContain('Add national ID')
    expect(wrapper.text()).not.toContain('Request a change')
    wrapper.unmount()
  })

  it('opens the completion modal for the national ID from the add button', async () => {
    const { wrapper, globals } = await mountCard({ hasNationalId: false }, [], 'en', {
      roles: ['entity'],
    })
    await wrapper.find('[data-testid="national-id-add"]').trigger('click')
    const completion = useAccountCompletionStore(globals.pinia)
    expect(completion.dialogOpen).toBe(true)
    expect(completion.asksNationalId).toBe(true)
    wrapper.unmount()
  })

  it('shows a first entry awaiting approval, offers no second entry and clears it on cancel', async () => {
    identityService.cancelChange.mockResolvedValue({
      data: { ...FIRST_ENTRY, status: 'cancelled' },
    })
    const { wrapper, globals } = await mountCard(
      { hasNationalId: false, nationalIdRequired: true, nationalIdStatus: 'pending' },
      [FIRST_ENTRY],
      'ar',
    )
    const t = globals.i18n.global.t
    expect(wrapper.find('[data-testid="national-id-pending"]').text()).toBe(
      t('nationalIdentity.card.awaitingApproval'),
    )
    expect(wrapper.find('[data-testid="national-id-add"]').exists()).toBe(false)
    const cancel = wrapper
      .findAll('button')
      .find((button) => button.text() === t('nationalIdentity.card.cancel'))
    await cancel.trigger('click')
    await flushPromises()
    expect(useAuthStore(globals.pinia).nationalIdStatus).toBe('missing')
    wrapper.unmount()
  })

  it('shows a pending request and lets the user cancel it', async () => {
    identityService.cancelChange.mockResolvedValue({ data: { ...PENDING, status: 'cancelled' } })
    const { wrapper } = await mountCard(
      { hasNationalId: true, nationalIdMasked: '••••••0008' },
      [PENDING],
      'en',
    )
    expect(wrapper.text()).toContain('under review')
    expect(wrapper.text()).not.toContain('Request a change')
    const cancel = wrapper.findAll('button').find((button) => button.text() === 'Cancel request')
    await cancel.trigger('click')
    await flushPromises()
    expect(identityService.cancelChange).toHaveBeenCalledWith('c1')
    wrapper.unmount()
  })
})
