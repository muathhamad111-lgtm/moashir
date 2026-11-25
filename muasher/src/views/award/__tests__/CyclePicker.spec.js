import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import CyclePicker from '@/views/award/CyclePicker.vue'
import { awardCycleService } from '@/services/awardCycleService'
import { useAuthStore } from '@/stores/auth'
import { useTableColumnsStore } from '@/stores/tableColumns'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/awardCycleService', () => ({ awardCycleService: { list: vi.fn() } }))

function cycleOf(id, status) {
  return {
    id,
    status,
    name: { ar: `دورة ${id}`, en: `Cycle ${id}` },
    year: 2026,
    committeeEnd: '2027-02-01T00:00:00Z',
  }
}

async function mountPicker() {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(sessionUserFor(['system_admin']))
  await globals.router.push('/admin/award/filtering')
  // Record the redirect without performing it: a real navigation would start loading the lazy target page,
  // which can still be in flight when the test environment tears down.
  const replace = vi.spyOn(globals.router, 'replace').mockResolvedValue(undefined)
  const wrapper = mount(CyclePicker, { global: { ...globals, stubs: { teleport: true } } })
  await flushPromises()
  return { wrapper, replace }
}

beforeEach(() => {
  vi.clearAllMocks()
  window.localStorage.clear()
})

describe('award cycle picker with concurrent cycles', () => {
  it('lists several cycles in progress and does not open any of them by itself', async () => {
    awardCycleService.list.mockResolvedValue({
      data: [
        cycleOf(41, 'submission-open'),
        cycleOf(40, 'submission-open'),
        cycleOf(31, 'committee-assessment'),
      ],
    })
    const { wrapper, replace } = await mountPicker()

    expect(replace).not.toHaveBeenCalled()
    expect(wrapper.findAll('.cycle-table__link')).toHaveLength(3)
    expect(wrapper.text()).toContain('Cycle 41')
    expect(wrapper.text()).toContain('Cycle 40')
  })

  it('shows the committee start and the request counts once the viewer chooses them', async () => {
    const cycle = {
      ...cycleOf(41, 'committee-assessment'),
      committeeStart: '2026-12-20T06:00:00Z',
      counts: { requests: 12, qualified: 4 },
    }
    awardCycleService.list.mockResolvedValue({ data: [cycle, cycleOf(30, 'closed')] })
    const { wrapper } = await mountPicker()
    useTableColumnsStore().choices = {
      'award-cycle-picker': ['status', 'committeeStart', 'counts'],
    }
    await flushPromises()

    const cell = (label) => wrapper.find(`tbody td[data-label="${label}"]`)
    expect(cell('Committee start').find('time').attributes('datetime')).toBe(cycle.committeeStart)
    expect(cell('Requests').text()).toBe('12 requests, 4 qualified')
  })

  it('still opens the only cycle in progress directly', async () => {
    awardCycleService.list.mockResolvedValue({
      data: [cycleOf(41, 'submission-open'), cycleOf(30, 'closed')],
    })
    const { replace } = await mountPicker()

    expect(replace).toHaveBeenCalledWith({
      name: 'award.filtering.queue',
      params: { id: 41 },
      query: {},
    })
  })
})
