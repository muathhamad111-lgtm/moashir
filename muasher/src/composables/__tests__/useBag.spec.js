import { describe, expect, it, vi, beforeEach } from 'vitest'
import { defineComponent, h } from 'vue'
import { createI18n } from 'vue-i18n'
import { flushPromises, mount } from '@vue/test-utils'
import { useBagConsultant, useBagReassessment } from '@/composables/useBag'
import { bagService } from '@/services/bagService'

vi.mock('@/services/bagService', () => ({
  bagService: {
    getConsultantView: vi.fn(),
    listStages: vi.fn(),
    updateGapGuidance: vi.fn(),
    getReassessment: vi.fn(),
  },
}))

const i18n = createI18n({ legacy: false, locale: 'en', fallbackLocale: 'en', messages: { en: {}, ar: {} } })

function harness(composable) {
  let api
  const Host = defineComponent({
    setup() {
      api = composable()
      return () => h('div')
    },
  })
  mount(Host, { global: { plugins: [i18n] } })
  return api
}

const gapsFixture = [
  { id: 'gap-101', subCriterionId: 101, priority: null, status: 'approved', consultantNote: null, recommendedAction: null, open: false, helperFiles: [] },
  { id: 'gap-503', subCriterionId: 503, priority: 'high', status: 'approved', consultantNote: { ar: 'n', en: 'n' }, recommendedAction: null, open: true, helperFiles: [] },
]

function envelope(data) {
  return { data, meta: {}, errors: null }
}

beforeEach(() => {
  vi.clearAllMocks()
  bagService.getConsultantView.mockResolvedValue(
    envelope({
      subscription: { activeStageId: 'stage-1' },
      source: { kind: 'accredited' },
      reference: { assessmentId: 9102, version: 1 },
      stages: [{ id: 'stage-1', status: 'active' }],
      gaps: gapsFixture.map((g) => ({ ...g })),
    }),
  )
  bagService.listStages.mockResolvedValue(envelope([{ id: 'stage-1', status: 'active' }]))
})

describe('useBagConsultant — saveGuidance', () => {
  it('persists guidance and optimistically updates the local gap', async () => {
    bagService.updateGapGuidance.mockResolvedValue(
      envelope({
        id: 'gap-101',
        priority: 'medium',
        status: 'deferred',
        consultantNote: { ar: 'ملاحظة', en: 'note' },
        recommendedAction: { ar: 'إجراء', en: 'action' },
      }),
    )

    const api = harness(useBagConsultant)
    await api.load()
    await flushPromises()

    const gap = api.gaps.value.find((g) => g.id === 'gap-101')
    await api.saveGuidance(gap, { priority: 'medium', status: 'deferred' })
    await flushPromises()

    expect(bagService.updateGapGuidance).toHaveBeenCalledWith(
      expect.objectContaining({ gapId: 'gap-101', priority: 'medium', status: 'deferred' }),
    )
    const updated = api.gaps.value.find((g) => g.id === 'gap-101')
    expect(updated.priority).toBe('medium')
    expect(updated.status).toBe('deferred')
    expect(updated.recommendedAction.en).toBe('action')
    expect(api.savingGuidance['gap-101']).toBe(false)
  })
})

describe('useBagReassessment — grouping', () => {
  it('splits criteria into kit-scope vs rest and exposes the overall diff', async () => {
    bagService.getReassessment.mockResolvedValue(
      envelope({
        available: true,
        reference: { version: 1 },
        reassessment: { version: 2 },
        overall: { beforePercent: 78, afterPercent: 82, diffPercent: 4, closed: 2, improved: 1, stable: 14, regressed: 1 },
        criteria: [
          { subCriterionId: 503, outcome: 'closed', inKitScope: true, weight: 250, before: 150, after: 250, diff: 100, gapAfter: 0 },
          { subCriterionId: 101, outcome: 'stable', inKitScope: false, weight: 400, before: 328, after: 328, diff: 0, gapAfter: 72 },
        ],
      }),
    )

    const api = harness(useBagReassessment)
    await api.load()
    await flushPromises()

    expect(api.status.value).toBe('success')
    expect(api.kitCriteria.value.map((c) => c.subCriterionId)).toEqual([503])
    expect(api.otherCriteria.value.map((c) => c.subCriterionId)).toEqual([101])
    expect(api.overall.value.diffPercent).toBe(4)
    expect(api.reference.value.version).toBe(1)
    expect(api.reassessment.value.version).toBe(2)
  })

  it('surfaces an error status when the service rejects', async () => {
    bagService.getReassessment.mockRejectedValue({ normalized: { message: 'boom' } })
    const api = harness(useBagReassessment)
    await api.load()
    await flushPromises()
    expect(api.status.value).toBe('error')
    expect(api.errorMessage.value).toBe('boom')
  })
})
