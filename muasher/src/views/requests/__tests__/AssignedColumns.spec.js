import { describe, expect, it, vi } from 'vitest'
import { reactive, ref } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import Assigned from '@/views/requests/Assigned.vue'
import { useTableColumnsStore } from '@/stores/tableColumns'
import { createTestGlobals } from '@/test-utils'

const ROW = {
  id: 9101,
  number: 'ACC-2026-000058',
  path: 'accredited',
  pathLabel: { ar: 'التقييم المعتمد', en: 'Accredited assessment' },
  status: 'under-review',
  entity: { id: 6002, reference: 'ENT-006002', name: { ar: 'جمعية إحسان' }, region: null },
  assignedAt: '2026-11-02T08:00:00Z',
  startedAt: '2026-11-03T08:00:00Z',
  completionDeadline: null,
  awardCycle: { id: 3, name: { ar: 'دورة 2026', en: '2026 cycle' }, year: 2026 },
  reviewProgress: { scored: 4, required: 27 },
  submittedAt: '2026-11-01T08:00:00Z',
  dueAt: '2026-11-15T08:00:00Z',
  isOverdue: false,
  completedAt: null,
}

vi.mock('@/composables/useAssignedRequests', () => ({
  useAssignedRequests: () => ({
    items: ref([ROW]),
    status: ref('success'),
    errorMessage: ref(''),
    load: vi.fn(),
    filters: reactive({ tab: '' }),
    sort: ref('due_at'),
    setFilter: vi.fn(),
    setSort: vi.fn(),
    path: ref('accredited'),
    pathCounts: ref([]),
    paginator: { pagination: ref({ page: 1, lastPage: 1 }), setPage: vi.fn() },
  }),
}))

// Columns v2: the path, cycle, dates and entity reference the assigned row carries, once chosen.
describe('assigned requests optional columns', () => {
  it('shows the cycle, the review start and the entity reference once the viewer adds them', async () => {
    const wrapper = mount(Assigned, { global: createTestGlobals({ locale: 'en' }) })
    await flushPromises()
    useTableColumnsStore().choices = {
      'requests-assigned': ['cycle', 'startedAt', 'entityReference'],
    }
    await flushPromises()
    const cells = wrapper.findAll('tbody td')
    expect(cells[1].text()).toBe('2026 cycle 2026')
    expect(cells[2].find('time').attributes('datetime')).toBe('2026-11-03T08:00:00Z')
    expect(cells[3].text()).toBe('ENT-006002')
  })
})
