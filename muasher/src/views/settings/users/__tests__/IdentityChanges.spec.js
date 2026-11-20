import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import IdentityChanges from '@/views/settings/users/IdentityChanges.vue'
import { identityService } from '@/services/identityService'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/identityService', () => ({
  identityService: {
    reviewQueue: vi.fn(),
    reviewDetail: vi.fn(),
    approve: vi.fn(),
    reject: vi.fn(),
  },
}))

const CHANGE = {
  id: 'c1',
  status: 'pending',
  fields: ['name', 'national_id'],
  user: { id: 31, name: 'سلمان المطيري' },
  submittedBy: { id: 31, name: 'سلمان المطيري' },
  onBehalf: false,
  currentName: 'سلمان المطيري',
  requestedName: 'سلمان بن فهد المطيري',
  currentNationalIdMasked: '••••••0008',
  requestedNationalIdMasked: '••••••0006',
  reason: 'تصحيح خطأ إملائي في الاسم',
  reviewedBy: null,
  reviewNote: null,
  createdAt: '2026-11-20T10:00:00Z',
  decidedAt: null,
}

async function mountQueue(locale = 'en') {
  const globals = createTestGlobals({ locale })
  useAuthStore(globals.pinia).setUser(sessionUserFor(['system_admin']))
  const wrapper = mount(IdentityChanges, { global: globals, attachTo: document.body })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  vi.clearAllMocks()
  identityService.reviewQueue.mockResolvedValue({
    data: [CHANGE],
    meta: { pagination: { page: 1, perPage: 10, total: 1, lastPage: 1 } },
  })
  identityService.reviewDetail.mockResolvedValue({
    data: { ...CHANGE, requestedNationalId: '2000000006' },
  })
})

describe('identity change review queue', () => {
  it.each(['ar', 'en'])('lists pending requests with what they change (%s)', async (locale) => {
    const wrapper = await mountQueue(locale)
    expect(identityService.reviewQueue).toHaveBeenCalled()
    expect(wrapper.text()).toContain('سلمان المطيري')
    wrapper.unmount()
  })

  it('opens a request to show the requested number in full', async () => {
    const wrapper = await mountQueue()
    await wrapper.find('button[aria-label*="View request"]').trigger('click')
    await flushPromises()
    expect(identityService.reviewDetail).toHaveBeenCalledWith('c1')
    expect(
      document.body.querySelector('[data-testid="requested-national-id"]').textContent,
    ).toContain('2000000006')
    wrapper.unmount()
  })
})
