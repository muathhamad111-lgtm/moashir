import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { awardCycleService } from '@/services/awardCycleService'
import { advancedService } from '@/services/advancedService'
import { filteringService } from '@/services/filteringService'
import { useAdvancedIntro } from '@/composables/useAdvancedIntro'
import { useCycleEditor } from '@/composables/useCycleEditor'
import { useCycleTransitions } from '@/composables/useCycleTransitions'
import { useFilteringDetail } from '@/composables/useFilteringDetail'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import { withSetup } from '@/test-utils/withSetup'

vi.mock('@/services/awardCycleService', () => ({
  awardCycleService: { getCurrent: vi.fn(), get: vi.fn(), getDefaultConditions: vi.fn(), open: vi.fn(), close: vi.fn(), closeSubmissions: vi.fn(), reopen: vi.fn() },
}))
vi.mock('@/services/advancedService', () => ({ advancedService: { getCurrent: vi.fn(), apply: vi.fn(), deleteDraft: vi.fn() } }))
vi.mock('@/services/filteringService', () => ({ filteringService: { get: vi.fn() } }))
vi.mock('@/services/regionsService', () => ({ regionsService: { listRegions: vi.fn().mockResolvedValue({ data: [{ id: 1 }] }) } }))
vi.mock('@/services/assessmentsService', () => ({ assessmentsService: { getPublishedCriteria: vi.fn().mockResolvedValue({ data: { versionId: 1, versionNumber: 1, scaleKind: 'of-10' } }) } }))
vi.mock('@/composables/useConfirm', () => ({ useConfirm: () => ({ confirm: vi.fn().mockResolvedValue(true) }) }))

function failure(status, code, meta = {}) {
  const error = new Error(code)
  error.normalized = { status, code, meta, message: code, fieldErrors: {} }
  return error
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useAdvancedIntro', () => {
  it('loads the public cycle for guests and the eligibility for an entity', async () => {
    awardCycleService.getCurrent.mockResolvedValue({ data: null })
    const guest = withSetup(() => useAdvancedIntro())
    await guest.api.load()
    expect(guest.api.cycleStatus.value).toBe('empty')
    expect(advancedService.getCurrent).not.toHaveBeenCalled()
    const entity = withSetup(() => useAdvancedIntro())
    useAuthStore(entity.globals.pinia).setUser(sessionUserFor(['entity']))
    advancedService.getCurrent.mockResolvedValue({ data: { cycle: { id: 31 }, eligibility: { eligible: true }, request: null, previous: null } })
    await entity.api.load()
    expect(entity.api.status.value).toBe('ready')
  })

  it('keeps the conditions failure of an application and reloads the current state', async () => {
    const entity = withSetup(() => useAdvancedIntro())
    useAuthStore(entity.globals.pinia).setUser(sessionUserFor(['entity']))
    advancedService.getCurrent.mockResolvedValue({ data: { cycle: { id: 31 }, eligibility: { eligible: true }, request: null, previous: null } })
    await entity.api.loadCurrent()
    advancedService.apply.mockRejectedValue(failure(422, 'cycle.conditions_failed', { conditions: [{ rule: 'region', passed: false }] }))
    expect(await entity.api.apply()).toBeNull()
    expect(entity.api.rejection.value).toEqual({ code: 'cycle.conditions_failed', conditions: [{ rule: 'region', passed: false }] })
    expect(advancedService.getCurrent).toHaveBeenCalledTimes(2)
    advancedService.apply.mockResolvedValue({ data: { id: 9300 } })
    expect(await entity.api.apply()).toEqual({ id: 9300 })
    expect(advancedService.apply).toHaveBeenLastCalledWith({ awardCycleId: 31 })
  })
})

describe('cycle editor and transitions', () => {
  it('loads a new cycle with the defaults and an existing one with its locks and the running cycle', async () => {
    awardCycleService.getDefaultConditions.mockResolvedValue({ data: { minEntityAgeYears: 3, disqualificationScore: '60.00', initialReviewSlaDays: 14 } })
    awardCycleService.getCurrent.mockResolvedValue({ data: { id: 31, name: { en: 'Cycle' }, year: 2026 } })
    const fresh = withSetup(() => useCycleEditor(ref(null)))
    await fresh.api.load()
    expect(fresh.api.initialValues.value).toMatchObject({ disqualificationScore: '60.00', criteriaVersionId: 1 })
    expect(fresh.api.blockingCycle.value).toMatchObject({ id: 31 })
    awardCycleService.get.mockResolvedValue({ data: { id: 32, status: 'submission-open', name: { en: 'Next' }, regions: [], conditions: {}, members: [], initialAssessors: [] } })
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
    awardCycleService.close.mockRejectedValue(failure(409, 'cycle.requests_pending', { openRequests: [{ id: 9203 }] }))
    expect(await api.confirmAndRun('close')).toBe(false)
    expect(api.blocked.value).toEqual({ code: 'cycle.requests_pending', requests: [{ id: 9203 }] })
    awardCycleService.closeSubmissions.mockResolvedValue({ data: { id: 31, status: 'submission-closed' } })
    expect(await api.confirmAndRun('closeSubmissions')).toBe(true)
    expect(editor.apply).toHaveBeenCalledWith({ id: 31, status: 'submission-closed' })
    const cancelled = withSetup(() => useCycleTransitions({ editor, reasonExtra: async () => null }))
    expect(await cancelled.api.confirmAndRun('open')).toBe(false)
    expect(awardCycleService.open).not.toHaveBeenCalled()
  })
})

describe('useFilteringDetail', () => {
  it('lets a filtering officer decide only on an awaiting request once the window is open', async () => {
    filteringService.get.mockResolvedValue({ data: { row: { id: 9205, status: 'awaiting-filtering', isFrozen: false } } })
    awardCycleService.get.mockResolvedValue({ data: { id: 31, status: 'submission-open' } })
    const officer = withSetup(() => useFilteringDetail(ref(31), ref(9205)))
    useAuthStore(officer.globals.pinia).setUser(sessionUserFor(['filtering_officer']))
    await officer.api.load()
    expect([officer.api.mayDecide.value, officer.api.windowOpen.value]).toEqual([true, false])
    const admin = withSetup(() => useFilteringDetail(ref(31), ref(9205)))
    useAuthStore(admin.globals.pinia).setUser(sessionUserFor(['system_admin']))
    await admin.api.load()
    expect(admin.api.mayDecide.value).toBe(false)
    filteringService.get.mockRejectedValue(failure(403, 'forbidden'))
    await admin.api.load()
    expect(admin.api.status.value).toBe('blocked')
  })
})
