import { beforeEach, describe, expect, it, vi } from 'vitest'
import { computed, defineComponent, ref } from 'vue'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestGlobals } from '@/test-utils'
import { awardCycleService } from '@/services/awardCycleService'
import { advancedService } from '@/services/advancedService'
import { filteringService } from '@/services/filteringService'
import { useAdvancedIntro } from '@/composables/useAdvancedIntro'
import { useAwardApplication } from '@/composables/useAwardApplication'
import { useAwardCycleChoice } from '@/composables/useAwardCycleChoice'
import { useCycleEditor } from '@/composables/useCycleEditor'
import { useCycleTransitions } from '@/composables/useCycleTransitions'
import { useFilteringDetail } from '@/composables/useFilteringDetail'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import { withSetup } from '@/test-utils/withSetup'

vi.mock('@/services/awardCycleService', () => ({
  awardCycleService: {
    getCurrent: vi.fn(),
    listOpen: vi.fn(),
    get: vi.fn(),
    getDefaultConditions: vi.fn(),
    open: vi.fn(),
    close: vi.fn(),
    closeSubmissions: vi.fn(),
    reopen: vi.fn(),
  },
}))
vi.mock('@/services/advancedService', () => ({
  advancedService: { getCurrent: vi.fn(), apply: vi.fn(), deleteDraft: vi.fn() },
}))
vi.mock('@/services/filteringService', () => ({ filteringService: { get: vi.fn() } }))
vi.mock('@/services/regionsService', () => ({
  regionsService: { listRegions: vi.fn().mockResolvedValue({ data: [{ id: 1 }] }) },
}))
vi.mock('@/services/assessmentsService', () => ({
  assessmentsService: {
    getPublishedCriteria: vi
      .fn()
      .mockResolvedValue({ data: { versionId: 1, versionNumber: 1, scaleKind: 'of-10' } }),
  },
}))
vi.mock('@/composables/useConfirm', () => ({
  useConfirm: () => ({ confirm: vi.fn().mockResolvedValue(true) }),
}))

function failure(status, code, meta = {}) {
  const error = new Error(code)
  error.normalized = { status, code, meta, message: code, fieldErrors: {} }
  return error
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useAdvancedIntro', () => {
  it('loads the public cycle without any entity call', async () => {
    awardCycleService.getCurrent.mockResolvedValue({ data: null })
    const guest = withSetup(() => useAdvancedIntro())
    await guest.api.load()
    expect(guest.api.cycleStatus.value).toBe('empty')
    expect(advancedService.getCurrent).not.toHaveBeenCalled()
    awardCycleService.getCurrent.mockRejectedValue(failure(500, 'server_error'))
    await guest.api.load()
    expect(guest.api.cycleStatus.value).toBe('error')
  })
})

const NORTH = { id: 41, name: { en: 'North' }, year: 2026 }
const SOUTH = { id: 40, name: { en: 'South' }, year: 2026 }

async function setupAt(path, composable) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  await globals.router.push(path)
  let api
  mount(
    defineComponent({
      setup() {
        api = composable()
        return () => null
      },
    }),
    { global: { plugins: globals.plugins } },
  )
  return api
}

describe('award cycle choice', () => {
  it('chooses the only open cycle by default and shows no chooser', async () => {
    const api = await setupAt('/assessment/advanced', () =>
      useAwardCycleChoice(
        ref([NORTH]),
        computed(() => null),
      ),
    )
    expect(api.showChooser.value).toBe(false)
    expect(api.selectedId.value).toBe(41)
  })

  it('lists several open cycles, prefers the cycle in the link, then the fallback, then the choice', async () => {
    const choices = ref([NORTH, SOUTH])
    const fallback = computed(() => 41)
    const linked = await setupAt('/paths/award?cycle=40', () =>
      useAwardCycleChoice(choices, fallback),
    )
    expect(linked.showChooser.value).toBe(true)
    expect(linked.selectedId.value).toBe(40)
    const plain = await setupAt('/paths/award?cycle=99', () =>
      useAwardCycleChoice(choices, fallback),
    )
    expect(plain.selectedId.value).toBe(41)
    plain.choose(40)
    expect(plain.selected.value).toEqual(SOUTH)
  })

  it('lets a visitor of the intro browse the open cycles', async () => {
    awardCycleService.getCurrent.mockResolvedValue({ data: NORTH })
    awardCycleService.listOpen.mockResolvedValue({ data: [NORTH, SOUTH] })
    const guest = withSetup(() => useAdvancedIntro())
    await guest.api.load()
    expect(guest.api.showChooser.value).toBe(true)
    expect(guest.api.displayedCycle.value).toEqual(NORTH)
    guest.api.selectCycle(40)
    expect(guest.api.displayedCycle.value).toEqual(SOUTH)
    awardCycleService.listOpen.mockResolvedValue({ data: [NORTH] })
    await guest.api.load()
    expect(guest.api.showChooser.value).toBe(false)
  })
})

describe('useAwardApplication', () => {
  it('applies for the cycle and returns the opened draft', async () => {
    const entity = withSetup(() => useAwardApplication())
    advancedService.apply.mockResolvedValue({ data: { id: 9300 } })
    expect(await entity.api.apply(31)).toEqual({ id: 9300 })
    expect(advancedService.apply).toHaveBeenLastCalledWith({ awardCycleId: 31 })
    expect(await entity.api.apply(null)).toBeNull()
    expect(advancedService.apply).toHaveBeenCalledTimes(1)
  })

  it('keeps a conditions failure for the page to reload', async () => {
    const entity = withSetup(() => useAwardApplication())
    advancedService.apply.mockRejectedValue(
      failure(422, 'cycle.conditions_failed', { conditions: [{ rule: 'region', passed: false }] }),
    )
    expect(await entity.api.apply(31)).toBeNull()
    expect(entity.api.rejection.value).toEqual({
      code: 'cycle.conditions_failed',
      conditions: [{ rule: 'region', passed: false }],
    })
  })

  it('sends a paid cycle to its checkout instead of refusing', async () => {
    const entity = withSetup(() => useAwardApplication())
    const push = vi.spyOn(entity.globals.router, 'push').mockResolvedValue(undefined)
    advancedService.apply.mockRejectedValue(
      failure(402, 'path.payment_required', { awardCycleId: 31 }),
    )
    expect(await entity.api.apply(31)).toBeNull()
    expect(entity.api.rejection.value).toBeNull()
    expect(push).toHaveBeenCalledWith({ name: 'assessment.advanced.pay', query: { cycle: '31' } })
  })
})

describe('cycle editor and transitions', () => {
  it('loads a new cycle with the defaults and an existing one with its locks and its open block', async () => {
    awardCycleService.getDefaultConditions.mockResolvedValue({
      data: { minEntityAgeYears: 3, disqualificationScore: '60.00', initialReviewSlaDays: 14 },
    })
    const fresh = withSetup(() => useCycleEditor(ref(null)))
    await fresh.api.load()
    expect(fresh.api.initialValues.value).toMatchObject({
      disqualificationScore: '60.00',
      description: { ar: '', en: '' },
    })
    expect(fresh.api.initialValues.value).not.toHaveProperty('criteriaVersionId')
    expect(fresh.api).not.toHaveProperty('published')
    expect(fresh.api.openBlock.value).toBeNull()
    expect(awardCycleService.getCurrent).not.toHaveBeenCalled()
    const block = {
      code: 'cycle.another_open_for_submission',
      concurrency: 'staggered',
      cycle: { id: 31, name: { en: 'Cycle' }, year: 2026, status: 'submission-open' },
    }
    awardCycleService.get.mockResolvedValueOnce({
      data: { id: 33, status: 'draft', regions: [], conditions: {}, openBlock: block },
    })
    const blocked = withSetup(() => useCycleEditor(ref(33)))
    await blocked.api.load()
    expect(blocked.api.openBlock.value).toEqual(block)
    awardCycleService.get.mockResolvedValue({
      data: {
        id: 32,
        status: 'submission-open',
        name: { en: 'Next' },
        regions: [],
        conditions: {},
        members: [],
        initialAssessors: [],
      },
    })
    const existing = withSetup(() => useCycleEditor(ref(32)))
    await existing.api.load()
    expect(existing.api.locked.value.has('submissionStart')).toBe(true)
    existing.api.lockFields(['committee_end'])
    expect(existing.api.locked.value.has('committeeEnd')).toBe(true)
    awardCycleService.get.mockRejectedValue(failure(404, 'not_found'))
    await existing.api.load()
    expect(existing.api.status.value).toBe('missing')
  })

  it('keeps the blocking requests of a refused close and asks the Super Admin for a reason', async () => {
    const editor = { cycle: ref({ id: 31 }), apply: vi.fn() }
    const { api } = withSetup(() => useCycleTransitions({ editor, reasonExtra: async () => ({}) }))
    awardCycleService.close.mockRejectedValue(
      failure(409, 'cycle.requests_pending', { openRequests: [{ id: 9203 }] }),
    )
    expect(await api.confirmAndRun('close')).toBe(false)
    expect(api.blocked.value).toEqual({ code: 'cycle.requests_pending', requests: [{ id: 9203 }] })
    awardCycleService.closeSubmissions.mockResolvedValue({
      data: { id: 31, status: 'submission-closed' },
    })
    expect(await api.confirmAndRun('closeSubmissions')).toBe(true)
    expect(editor.apply).toHaveBeenCalledWith({ id: 31, status: 'submission-closed' })
    const cancelled = withSetup(() =>
      useCycleTransitions({ editor, reasonExtra: async () => null }),
    )
    expect(await cancelled.api.confirmAndRun('open')).toBe(false)
    expect(awardCycleService.open).not.toHaveBeenCalled()
  })
})

describe('useFilteringDetail', () => {
  it('lets a filtering officer decide only on an awaiting request once the window is open', async () => {
    filteringService.get.mockResolvedValue({
      data: { row: { id: 9205, status: 'awaiting-filtering', isFrozen: false } },
    })
    awardCycleService.get.mockResolvedValue({ data: { id: 31, status: 'submission-open' } })
    const officer = withSetup(() => useFilteringDetail(ref(31), ref(9205)))
    useAuthStore(officer.globals.pinia).setUser(sessionUserFor(['filtering_officer']))
    await officer.api.load()
    // Decisions are open while submissions are open (operator, 2026-11-22); a draft cycle keeps them closed.
    expect([officer.api.mayDecide.value, officer.api.windowOpen.value]).toEqual([true, true])
    awardCycleService.get.mockResolvedValue({ data: { id: 31, status: 'draft' } })
    await officer.api.load()
    expect(officer.api.windowOpen.value).toBe(false)
    const admin = withSetup(() => useFilteringDetail(ref(31), ref(9205)))
    useAuthStore(admin.globals.pinia).setUser(sessionUserFor(['system_admin']))
    await admin.api.load()
    expect(admin.api.mayDecide.value).toBe(false)
    filteringService.get.mockRejectedValue(failure(403, 'forbidden'))
    await admin.api.load()
    expect(admin.api.status.value).toBe('blocked')
  })
})
