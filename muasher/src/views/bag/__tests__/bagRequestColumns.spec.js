import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Requests from '@/views/bag/Requests.vue'
import EvaluatorRequests from '@/views/bag/EvaluatorRequests.vue'
import { bagRequestsService } from '@/services/bagRequestsService'
import { useTableColumnsStore } from '@/stores/tableColumns'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/bagRequestsService', () => ({
  bagRequestsService: { list: vi.fn(), listForEvaluator: vi.fn(), listEvaluators: vi.fn() },
}))

const ROW = {
  id: 4,
  entityId: 6002,
  entityName: { ar: 'جمعية إحسان', en: 'Ihsan Association' },
  status: 'active',
  referenceSnapshot: { assessmentId: 9101, version: 1, scorePercent: 79, completedAt: null },
  selfScorePercent: 79,
  advancedScorePercent: 81,
  hasPreviousBag: false,
  previousBagId: null,
  assignedEvaluatorId: 7001,
  assignedEvaluatorName: { ar: 'سلطان القرني', en: 'Sultan Al-Qarni' },
  paidAt: '2026-07-01T09:00:00+03:00',
  assignedAt: '2026-07-03T09:00:00+03:00',
  startedAt: '2026-07-04T09:00:00+03:00',
  completedAt: null,
}

async function mountWithColumns(view, tableKey, columns) {
  const wrapper = mount(view, { global: createTestGlobals({ locale: 'en' }) })
  await flushPromises()
  useTableColumnsStore().choices = { [tableKey]: columns }
  await flushPromises()
  return wrapper.findAll('tbody td')
}

// Columns v2: the context results and the dates the BAG row carries, once chosen.
describe('BAG request lists optional columns', () => {
  it('shows the context results and the request dates in the requests list', async () => {
    bagRequestsService.list.mockResolvedValue({ data: [ROW], meta: {} })
    const cells = await mountWithColumns(Requests, 'bag-requests', [
      'context',
      'startedAt',
      'completedAt',
    ])
    expect(cells[1].findAll('.bag-requests__context-chip')).toHaveLength(2)
    expect(cells[2].text()).toContain('2026')
    expect(cells[3].text()).toBe('—')
  })

  it('shows the payment and start dates in the evaluator list', async () => {
    bagRequestsService.listForEvaluator.mockResolvedValue({ data: [ROW], meta: {} })
    const cells = await mountWithColumns(EvaluatorRequests, 'bag-evaluator-requests', [
      'paidAt',
      'startedAt',
      'completedAt',
    ])
    expect(cells[1].text()).toContain('2026')
    expect(cells[2].text()).toContain('2026')
    expect(cells[3].text()).toBe('—')
  })
})
