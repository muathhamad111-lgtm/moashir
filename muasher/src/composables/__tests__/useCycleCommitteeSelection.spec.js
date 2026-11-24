import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import { useCycleCommittee } from '@/composables/useCycleCommittee'
import { useCycleCommitteeSelection } from '@/composables/useCycleCommitteeSelection'
import { useCycleStaff } from '@/composables/useCycleStaff'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import { withSetup } from '@/test-utils/withSetup'

const committee = vi.hoisted(() => ({
  listMembers: vi.fn(),
  listCandidates: vi.fn(),
  appoint: vi.fn(),
  remove: vi.fn(),
}))
const cycles = vi.hoisted(() => ({
  syncMembers: vi.fn(),
  syncInitialAssessors: vi.fn(),
  listCandidates: vi.fn(),
}))

vi.mock('@/services/committeeService', () => ({ committeeService: committee }))
vi.mock('@/services/awardCycleService', () => ({ awardCycleService: cycles }))

const DAVID = { id: 27, name: 'David Nguyen' }
const MANAL = { id: 29, name: 'د. منال بنت سعيد الحارثي' }
const OMAR = { id: 30, name: 'عمر' }

function seat(id, user, status = 'active') {
  return { id, user, status }
}

function cycleOf(status = 'draft') {
  return { id: 101, status, revealsStaff: true, members: [], initialAssessors: [] }
}

function rosterReply(seats, canManage = true) {
  return { data: seats, meta: { canManage } }
}

async function selectionFor(cycle, { roles = ['system_admin'] } = {}) {
  const current = ref(cycle)
  const { api } = withSetup(() => {
    useAuthStore().setUser(sessionUserFor(roles))
    const roster = useCycleCommittee(current)
    return { roster, selection: useCycleCommitteeSelection({ cycle: current, roster }) }
  })
  await flushPromises()
  return { ...api, cycle: current }
}

function failure(message, fieldErrors = {}) {
  const error = new Error(message)
  error.normalized = { status: 422, message, fieldErrors }
  return error
}

beforeEach(() => {
  vi.clearAllMocks()
  committee.listCandidates.mockResolvedValue({ data: [DAVID, MANAL] })
  committee.appoint.mockResolvedValue({ data: {} })
  committee.remove.mockResolvedValue({ data: null })
})

describe('useCycleCommitteeSelection — the optional committee of the team step', () => {
  it('starts from the active roster and offers the candidates beside the seated members', async () => {
    committee.listMembers.mockResolvedValue(
      rosterReply([seat(1, OMAR), seat(2, MANAL, 'disabled')]),
    )
    const { roster, selection } = await selectionFor(cycleOf())
    expect(committee.listMembers).toHaveBeenCalledWith(101)
    expect(selection.selection.value).toEqual([OMAR.id])
    expect(roster.names.value).toEqual(['عمر'])
    expect(selection.dirty.value).toBe(false)
    await selection.search('Dav')
    expect(committee.listCandidates).toHaveBeenCalledWith(101, { search: 'Dav' })
    expect(selection.options.value.map((option) => option.value)).toEqual([
      DAVID.id,
      MANAL.id,
      OMAR.id,
    ])
  })

  it('appoints the added people, removes the dropped ones and passes the reason along', async () => {
    committee.listMembers.mockResolvedValue(rosterReply([seat(1, OMAR)]))
    const { selection } = await selectionFor(cycleOf())
    selection.select([DAVID.id, MANAL.id])
    expect(selection.dirty.value).toBe(true)
    await selection.save({ reason: 'سبب' })
    expect(committee.appoint.mock.calls).toEqual([
      [101, { userId: DAVID.id, reason: 'سبب' }],
      [101, { userId: MANAL.id, reason: 'سبب' }],
    ])
    expect(committee.remove).toHaveBeenCalledWith(101, 1, { reason: 'سبب' })
    expect(committee.listMembers).toHaveBeenCalledTimes(2)
  })

  it('sends nothing when the committee did not change', async () => {
    committee.listMembers.mockResolvedValue(rosterReply([seat(1, OMAR)]))
    const { selection } = await selectionFor(cycleOf())
    selection.select([OMAR.id])
    await selection.save({})
    expect(committee.appoint).not.toHaveBeenCalled()
    expect(committee.remove).not.toHaveBeenCalled()
  })

  it('accepts an empty committee', async () => {
    committee.listMembers.mockResolvedValue(rosterReply([]))
    const { selection } = await selectionFor(cycleOf())
    expect(selection.selection.value).toEqual([])
    expect(selection.dirty.value).toBe(false)
    await selection.save({})
    expect(committee.appoint).not.toHaveBeenCalled()
  })

  it('shows a refused appointment on the field, keeps the choice and reloads the roster', async () => {
    committee.listMembers.mockResolvedValue(rosterReply([]))
    committee.appoint
      .mockResolvedValueOnce({ data: {} })
      .mockRejectedValueOnce(
        failure('تعارض في فصل المهام.', { userId: ['لا يجمع الشخص بين الدورين.'] }),
      )
    const { selection } = await selectionFor(cycleOf())
    selection.select([DAVID.id, MANAL.id])
    committee.listMembers.mockResolvedValue(rosterReply([seat(5, DAVID)]))
    await expect(selection.save({})).rejects.toThrow('تعارض في فصل المهام.')
    expect(selection.fieldError.value).toBe('لا يجمع الشخص بين الدورين.')
    expect(selection.selection.value).toEqual([DAVID.id, MANAL.id])
    expect(selection.dirty.value).toBe(true)
    selection.select([DAVID.id, MANAL.id])
    expect(selection.fieldError.value).toBe('')
  })

  it.each(['committee-assessment', 'awaiting-secretary', 'approved', 'closed'])(
    'is read only once the committee assessment has started (%s)',
    async (status) => {
      committee.listMembers.mockResolvedValue(rosterReply([seat(1, OMAR)]))
      const { selection } = await selectionFor(cycleOf(status))
      expect(selection.stageLocked.value).toBe(true)
      expect(selection.locked.value).toBe(true)
      selection.select([DAVID.id])
      expect(selection.dirty.value).toBe(false)
      await selection.save({})
      expect(committee.appoint).not.toHaveBeenCalled()
      expect(committee.remove).not.toHaveBeenCalled()
    },
  )

  it('stays editable until the committee starts', async () => {
    committee.listMembers.mockResolvedValue(rosterReply([]))
    for (const status of ['draft', 'submission-open', 'submission-closed']) {
      const { selection } = await selectionFor(cycleOf(status))
      expect(selection.locked.value).toBe(false)
    }
  })

  it('is read only when the roster says the user does not manage it', async () => {
    committee.listMembers.mockResolvedValue(rosterReply([seat(1, OMAR)], false))
    const { selection } = await selectionFor(cycleOf())
    expect(selection.stageLocked.value).toBe(false)
    expect(selection.locked.value).toBe(true)
  })

  it('reads no roster for a user who may not see it, or before the cycle exists', async () => {
    await selectionFor(cycleOf(), { roles: ['assessor'] })
    await selectionFor({ ...cycleOf(), revealsStaff: false })
    const { roster } = await selectionFor(null)
    expect(committee.listMembers).not.toHaveBeenCalled()
    expect(roster.names.value).toBeNull()
  })

  it('loads the roster once the new cycle is created', async () => {
    committee.listMembers.mockResolvedValue(rosterReply([]))
    const { cycle } = await selectionFor(null)
    cycle.value = cycleOf()
    await flushPromises()
    expect(committee.listMembers).toHaveBeenCalledWith(101)
  })
})

describe('useCycleStaff — one save for the whole team step', () => {
  async function staffFor(reasonExtra = async () => ({})) {
    committee.listMembers.mockResolvedValue(rosterReply([]))
    const cycle = ref(cycleOf())
    const editor = { cycle, apply: vi.fn((detail) => (cycle.value = detail)) }
    const { api } = withSetup(() => {
      useAuthStore().setUser(sessionUserFor(['system_admin']))
      const roster = useCycleCommittee(cycle)
      const selection = useCycleCommitteeSelection({ cycle, roster })
      return { selection, staff: useCycleStaff({ editor, reasonExtra, committee: selection }) }
    })
    await flushPromises()
    return api
  }

  it('counts a committee change as an unsaved team change and saves it with the step', async () => {
    const { selection, staff } = await staffFor()
    expect(staff.dirty.value).toBe(false)
    selection.select([DAVID.id])
    expect(staff.dirty.value).toBe(true)
    committee.listMembers.mockResolvedValue(rosterReply([seat(5, DAVID)]))
    expect(await staff.saveAll()).toBe(true)
    expect(committee.appoint).toHaveBeenCalledWith(101, { userId: DAVID.id })
    expect(cycles.syncMembers).not.toHaveBeenCalled()
    expect(staff.dirty.value).toBe(false)
    expect(selection.selection.value).toEqual([DAVID.id])
  })

  it('asks the Super Admin reason once and keeps the step open on a refused appointment', async () => {
    const reasonExtra = vi.fn().mockResolvedValue({ reason: 'سبب معتمد' })
    const { selection, staff } = await staffFor(reasonExtra)
    selection.select([DAVID.id])
    committee.appoint.mockRejectedValueOnce(failure('refused', { userId: ['Not eligible.'] }))
    expect(await staff.saveAll()).toBe(false)
    expect(reasonExtra).toHaveBeenCalledTimes(1)
    expect(committee.appoint).toHaveBeenCalledWith(101, { userId: DAVID.id, reason: 'سبب معتمد' })
    expect(selection.fieldError.value).toBe('Not eligible.')
    expect(staff.dirty.value).toBe(true)
  })

  it('drops an unsaved committee choice when the step is reset', async () => {
    const { selection, staff } = await staffFor()
    selection.select([DAVID.id])
    staff.reset()
    expect(selection.selection.value).toEqual([])
    expect(staff.dirty.value).toBe(false)
  })
})
