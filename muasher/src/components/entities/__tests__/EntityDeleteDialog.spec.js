import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import EntityDeleteDialog from '@/components/entities/admin/EntityDeleteDialog.vue'
import { entityLifecycleService } from '@/services/entityLifecycleService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/entityLifecycleService', () => ({
  entityLifecycleService: { deletionImpact: vi.fn(), softDelete: vi.fn() },
}))

const ENTITY = {
  id: 6002,
  reference: 'ENT-006002',
  name: { ar: 'جمعية إحسان الخيرية', en: 'Ehsan Charitable Society' },
}
const LISTED = [
  {
    id: 31,
    number: 'ADV-2026-000031',
    path: 'advanced',
    status: 'under-initial-assessment',
    cycleId: null,
  },
  { id: 7, number: 'BAG-2026-000007', path: 'bag', status: 'stage-active', cycleId: null },
]
const CURRENT = [
  ...LISTED,
  { id: 52, number: 'ACC-2026-000052', path: 'accredited', status: 'submitted', cycleId: null },
]
const REASON = 'Merged with another entity by board decision'

function stale() {
  return Object.assign(new Error('conflict'), {
    normalized: {
      code: 'entity.open_requests_confirmation_required',
      status: 409,
      meta: { openRequests: CURRENT },
      fieldErrors: {},
    },
  })
}

async function mountDialog() {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  const wrapper = mount(EntityDeleteDialog, {
    props: { modelValue: true, entity: ENTITY },
    global: { ...globals, stubs: { teleport: true } },
    attachTo: document.body,
  })
  await flushPromises()
  return wrapper
}

async function settle() {
  for (let round = 0; round < 3; round += 1) {
    await new Promise((resolve) => setTimeout(resolve, 10))
    await flushPromises()
  }
}

async function submit(wrapper) {
  await wrapper.find('form').trigger('submit')
  await settle()
}

beforeEach(() => {
  vi.clearAllMocks()
  entityLifecycleService.deletionImpact.mockResolvedValue({
    data: { openRequests: LISTED, openRequestsCount: 2, requiresConfirmation: true },
  })
})

describe('EntityDeleteDialog', () => {
  it('lists the open requests and refuses to delete before they are confirmed', async () => {
    const wrapper = await mountDialog()
    expect(wrapper.findAll('.open-requests__row')).toHaveLength(2)
    await wrapper.find('[name="reason"]').setValue(REASON)
    await submit(wrapper)
    expect(entityLifecycleService.softDelete).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Confirm the listed open requests to continue')
    wrapper.unmount()
  })

  it('re-shows the current list on a stale confirmation and deletes after a new confirmation', async () => {
    entityLifecycleService.softDelete
      .mockRejectedValueOnce(stale())
      .mockResolvedValueOnce(undefined)
    const wrapper = await mountDialog()
    await wrapper.find('[name="reason"]').setValue(REASON)
    await wrapper.find('[name="confirmed"]').setValue(true)
    await submit(wrapper)
    expect(entityLifecycleService.softDelete).toHaveBeenCalledWith(6002, {
      reason: REASON,
      acknowledgedRequestNumbers: ['ADV-2026-000031', 'BAG-2026-000007'],
    })
    expect(wrapper.find('.entity-delete__stale').exists()).toBe(true)
    expect(wrapper.findAll('.open-requests__row')).toHaveLength(3)
    expect(wrapper.find('[name="confirmed"]').element.checked).toBe(false)

    await wrapper.find('[name="confirmed"]').setValue(true)
    await submit(wrapper)
    expect(entityLifecycleService.softDelete).toHaveBeenLastCalledWith(6002, {
      reason: REASON,
      acknowledgedRequestNumbers: CURRENT.map((request) => request.number),
    })
    expect(wrapper.emitted('deleted')).toHaveLength(1)
    wrapper.unmount()
  })

  it('deletes an entity without open requests with a reason only', async () => {
    entityLifecycleService.deletionImpact.mockResolvedValue({
      data: { openRequests: [], openRequestsCount: 0, requiresConfirmation: false },
    })
    entityLifecycleService.softDelete.mockResolvedValue(undefined)
    const wrapper = await mountDialog()
    expect(wrapper.find('.entity-delete__none').exists()).toBe(true)
    await wrapper.find('[name="reason"]').setValue(REASON)
    await submit(wrapper)
    expect(entityLifecycleService.softDelete).toHaveBeenCalledWith(6002, {
      reason: REASON,
      acknowledgedRequestNumbers: [],
    })
    wrapper.unmount()
  })
})
