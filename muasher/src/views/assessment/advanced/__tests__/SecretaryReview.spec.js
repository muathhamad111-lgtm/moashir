import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { ref } from 'vue'
import SecretaryReview from '@/views/assessment/advanced/SecretaryReview.vue'
import { createTestGlobals } from '@/test-utils'

const status = ref('success')
const entities = ref([])
const selected = ref(null)
const committeeAssessorsStatus = ref('idle')
const committeeAssessors = ref([])
const entityMeta = ref(null)
const detailStatus = ref('idle')
const detail = ref(null)

const selectEntity = vi.fn(async (row) => {
  selected.value = row
  committeeAssessorsStatus.value = 'success'
  committeeAssessors.value = [
    { committeeAssessorId: 'jur-9401', committeeAssessorName: { ar: 'مقيّم أ', en: 'Assessor A' }, status: 'completed', total: 90, substituteFor: null },
    { committeeAssessorId: 'jur-9402', committeeAssessorName: { ar: 'مقيّم ب', en: 'Assessor B' }, status: 'completed', total: 86, substituteFor: null },
  ]
  entityMeta.value = { average: 88 }
})

const openCommitteeAssessor = vi.fn(async (committeeAssessorId) => {
  detailStatus.value = 'success'
  detail.value = {
    committeeAssessorId,
    committeeAssessorName: { ar: 'مقيّم أ', en: 'Assessor A' },
    committeeAssessorStatus: 'completed',
    entityName: { ar: 'جهة', en: 'Entity' },
    total: 90,
    tree: [
      {
        id: 1,
        name: { ar: 'معيار', en: 'Main' },
        subs: [
          { id: 101, name: { ar: 'فرعي', en: 'Sub' }, evaluationType: 'direct', points: [] },
        ],
      },
    ],
    scores: { subs: { 101: 0.9 }, points: {} },
    notes: [{ subCriterionId: 101, text: 'Assessor A private note' }],
  }
})

const load = vi.fn(() => Promise.resolve())

vi.mock('@/composables/useCommittee', () => ({
  useSecretaryReview: () => ({
    status,
    errorMessage: ref(''),
    entities,
    selected,
    committeeAssessorsStatus,
    committeeAssessorsError: ref(''),
    committeeAssessors,
    entityMeta,
    detailStatus,
    detailError: ref(''),
    detail,
    localized: (v) => (v && (v.en ?? v.ar)) || '',
    load,
    selectEntity,
    clearEntity: vi.fn(),
    openCommitteeAssessor,
    retryDetail: vi.fn(),
    closeCommitteeAssessor: vi.fn(() => {
      detail.value = null
      detailStatus.value = 'idle'
    }),
  }),
}))

function mountView() {
  return mount(SecretaryReview, {
    attachTo: document.body,
    global: { ...createTestGlobals({ locale: 'en' }), stubs: { teleport: true } },
  })
}

beforeEach(() => {
  status.value = 'success'
  selected.value = null
  committeeAssessorsStatus.value = 'idle'
  committeeAssessors.value = []
  detailStatus.value = 'idle'
  detail.value = null
  entities.value = [
    {
      assessmentId: 9401,
      entityName: { ar: 'جهة', en: 'Entity' },
      committeeAssessorCount: 4,
      completedCount: 4,
      average: 88,
      secretaryStatus: 'pending',
      allCompleted: true,
    },
  ]
})
afterEach(() => {
  document.body.innerHTML = ''
})

describe('SecretaryReview.vue drill-down (Note 13)', () => {
  it('lists entities and a hint before any entity is selected', async () => {
    const wrapper = mountView()
    await flushPromises()
    expect(load).toHaveBeenCalled()
    expect(wrapper.text()).toContain('Entity')
    expect(wrapper.text()).toContain('Select an entity')
  })

  it('shows ALL assessors for a selected entity (aggregate review)', async () => {
    const wrapper = mountView()
    await flushPromises()
    await wrapper.find('.secretary-review__entities button').trigger('click')
    await flushPromises()
    expect(selectEntity).toHaveBeenCalled()
    const text = wrapper.text()
    expect(text).toContain('Assessor A')
    expect(text).toContain('Assessor B')
  })

  it('opens ONE assessor\'s full read-only detail with its private note', async () => {
    const wrapper = mountView()
    await flushPromises()
    await wrapper.find('.secretary-review__entities button').trigger('click')
    await flushPromises()
    const detailButtons = wrapper.findAll('.secretary-review__committee-assessors-panel button')
    const viewButton = detailButtons.find((b) => b.text().includes('View detail'))
    await viewButton.trigger('click')
    await flushPromises()
    expect(openCommitteeAssessor).toHaveBeenCalledWith('jur-9401')
    expect(wrapper.text()).toContain('Assessor A private note')
    expect(wrapper.text()).toContain('Read-only')
  })
})
