import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import NdaSettings from '@/views/settings/nda/NdaSettings.vue'
import NdaAcceptances from '@/views/settings/nda/NdaAcceptances.vue'
import { ndaService } from '@/services/ndaService'
import { useAuthStore } from '@/stores/auth'
import { toast } from '@/composables/useToast'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/ndaService', async (importOriginal) => {
  const actual = await importOriginal()
  const ndaService = {
    overview: vi.fn(),
    updateSettings: vi.fn(),
    versions: vi.fn(),
    version: vi.fn(),
    createDraft: vi.fn(),
    updateDraft: vi.fn(),
    discardDraft: vi.fn(),
    impact: vi.fn(),
    publish: vi.fn(),
    acceptances: vi.fn(),
    exportAcceptances: vi.fn(),
    sendReminders: vi.fn(),
  }
  const ndaDraftVersions = {
    list: () => ndaService.versions(),
    get: (id) => ndaService.version(id),
    createDraft: (_kind, payload) => ndaService.createDraft(payload),
    updateDraft: (_id, payload) => ndaService.updateDraft(payload),
    discardDraft: () => ndaService.discardDraft(),
    publish: (_id, payload) => ndaService.publish(payload),
  }
  return { ...actual, ndaService, ndaDraftVersions }
})

const CURRENT = {
  id: 501,
  version: '1.0',
  status: 'current',
  publishedAt: '2026-11-22T09:00:00Z',
  publishedBy: 'Munira',
  acceptancesCount: 21,
}
const DRAFT = {
  id: 502,
  version: '1.1',
  status: 'draft',
  publishedAt: null,
  acceptancesCount: null,
}
const OVERVIEW = {
  current: CURRENT,
  draft: DRAFT,
  roles: ['system_admin', 'assessor'],
  availableRoles: ['system_admin', 'assessor', 'finance_officer'],
  reminderDays: 3,
  maxReminderDays: 30,
  nextManualReminderAt: null,
}
const ROWS = [
  {
    user: { id: 1, name: 'Sara', email: 's@x', roles: ['assessor'] },
    status: 'accepted',
    acceptedAt: '2026-11-22T10:00:00Z',
    acceptedRoles: ['assessor'],
    ipMasked: '10.0.0.•',
    lastAcceptedVersion: '1.0',
  },
  {
    user: { id: 2, name: 'Noura', email: 'n@x', roles: ['assessor'] },
    status: 'pending',
    acceptedAt: null,
    acceptedRoles: [],
    ipMasked: null,
    lastAcceptedVersion: null,
  },
]

let wrapper

async function settle() {
  for (let round = 0; round < 3; round += 1) {
    await new Promise((resolve) => setTimeout(resolve, 10))
    await flushPromises()
  }
}

async function mountPage(component) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(sessionUserFor(['system_admin']))
  wrapper = mount(component, {
    global: { ...globals, stubs: { teleport: true } },
    attachTo: document.body,
  })
  await flushPromises()
  await flushPromises()
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.spyOn(toast, 'success').mockImplementation(() => {})
  vi.spyOn(toast, 'error').mockImplementation(() => {})
  ndaService.overview.mockResolvedValue({ data: OVERVIEW })
  ndaService.versions.mockResolvedValue({ data: [DRAFT, CURRENT] })
  ndaService.acceptances.mockResolvedValue({
    data: ROWS,
    meta: {
      summary: { inScope: 23, accepted: 21, pending: 2 },
      version: CURRENT,
      pagination: { lastPage: 1 },
    },
  })
  ndaService.impact.mockResolvedValue({
    data: { total: 23, roles: [{ role: 'assessor', count: 12 }] },
  })
  ndaService.updateSettings.mockResolvedValue({
    data: { ...OVERVIEW, roles: ['system_admin', 'assessor', 'finance_officer'] },
  })
})

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
})

describe('agreement settings page', () => {
  it('lists the versions with their acceptances against the staff in scope', async () => {
    await mountPage(NdaSettings)

    expect(wrapper.text()).toContain('21 of 23')
    expect(wrapper.text()).toContain('Munira')
    expect(wrapper.find('[data-testid="nda-open-publish"]').exists()).toBe(true)
  })

  it('saves the roles in scope with a reason', async () => {
    await mountPage(NdaSettings)
    const finance = wrapper
      .findAll('.nda-scope__chip')
      .find((chip) => chip.text().includes('Finance'))
    await finance.trigger('click')
    expect(finance.attributes('aria-pressed')).toBe('true')

    const save = wrapper.findAll('[data-testid="nda-scope"] button').at(-1)
    expect(save.attributes('disabled')).toBeUndefined()
    await save.trigger('click')
    await flushPromises()
    await wrapper.get('textarea[name="reason"]').setValue('Adding finance staff to the scope.')
    await wrapper
      .get('textarea[name="reason"]')
      .element.closest('form')
      .dispatchEvent(new Event('submit'))
    await settle()

    expect(ndaService.updateSettings).toHaveBeenCalledWith({
      roles: ['system_admin', 'assessor', 'finance_officer'],
      reminderDays: 3,
      reason: 'Adding finance staff to the scope.',
    })
  })

  it('shows the reach of a new version and refuses to publish without a reason and the confirmation', async () => {
    await mountPage(NdaSettings)
    await wrapper.get('[data-testid="nda-open-publish"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-testid="nda-publish"]').text()).toContain(
      '23 staff members will be asked to accept',
    )
    await wrapper
      .get('[data-testid="nda-publish"]')
      .element.closest('form')
      .dispatchEvent(new Event('submit'))
    await flushPromises()
    expect(ndaService.publish).not.toHaveBeenCalled()
  })
})

describe('acceptance register page', () => {
  it('shows the counts and the rows, masks the IP and filters by status', async () => {
    await mountPage(NdaAcceptances)

    expect(wrapper.text()).toContain('21')
    expect(wrapper.text()).toContain('10.0.0.•')
    expect(wrapper.text()).toContain('Pending 1.0')

    await wrapper.findAll('[role="radio"]').at(1).trigger('click')
    await flushPromises()
    expect(ndaService.acceptances).toHaveBeenLastCalledWith(
      expect.objectContaining({ filter: 'pending', page: 1 }),
    )
  })
})
