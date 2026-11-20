import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import NationalIdentityCard from '@/components/account/NationalIdentityCard.vue'
import { identityService } from '@/services/identityService'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/identityService', () => ({
  identityService: { myChanges: vi.fn(), submitChange: vi.fn(), cancelChange: vi.fn() },
}))

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

  it.each([
    [undefined, '/account'],
    ['/entity/profile', '/entity/profile'],
  ])('brings the user back to %s after adding the number', async (returnTo, expected) => {
    const props = returnTo ? { returnTo } : {}
    const { wrapper } = await mountCard({ hasNationalId: false }, [], 'en', {
      roles: ['entity'],
      props,
    })
    const link = wrapper.findAll('a').find((anchor) => anchor.text() === 'Add national ID')
    const target = new URL(link.attributes('href'), 'https://moashir.test')
    expect(target.pathname).toBe('/account/complete-identity')
    expect(target.searchParams.get('redirect')).toBe(expected)
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
