import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { computed, ref } from 'vue'
import CommitteeManagement from '@/views/assessment/advanced/CommitteeManagement.vue'
import { createTestGlobals } from '@/test-utils'

const state = {
  status: ref('success'),
  errorMessage: ref(''),
  committeeAssessors: ref([]),
  available: ref([]),
  qualifiedCount: ref(2),
  canRedistribute: computed(() => true),
  busy: ref(false),
  acting: ref(null),
}
const load = vi.fn(() => Promise.resolve())
const addCommitteeAssessor = vi.fn(() => Promise.resolve())
const removeCommitteeAssessor = vi.fn(() => Promise.resolve())
const reassign = vi.fn(() => Promise.resolve())
const redistribute = vi.fn(() => Promise.resolve())

vi.mock('@/composables/useCommittee', () => ({
  useCommitteeManagement: () => ({
    ...state,
    localized: (v) => (v && (v.en ?? v.ar)) || '',
    load,
    addCommitteeAssessor,
    removeCommitteeAssessor,
    reassign,
    redistribute,
  }),
}))

let canManage = true
vi.mock('@/composables/usePermissions', () => ({
  usePermissions: () => ({ can: (key) => (key === 'award.committee.manage' ? canManage : true) }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn() }),
}))
vi.mock('@/composables/useConfirm', () => ({
  useConfirm: () => ({ confirm: vi.fn(() => Promise.resolve(true)) }),
}))

function seedRoster() {
  state.committeeAssessors.value = [
    {
      id: 'jur-9401',
      name: { ar: 'مقيّم أ', en: 'Assessor A' },
      status: 'in-progress',
      assignmentCount: 2,
      completedCount: 1,
      substituteFor: null,
      assignments: [{ assessmentId: 9401, entityName: { ar: 'جهة', en: 'Entity' }, status: 'in-progress' }],
    },
  ]
  state.available.value = [{ id: 'jur-9501', name: { ar: 'مرشح', en: 'Candidate' } }]
}

function mountView() {
  return mount(CommitteeManagement, {
    attachTo: document.body,
    global: { ...createTestGlobals({ locale: 'en' }), stubs: { teleport: true } },
  })
}

beforeEach(() => {
  canManage = true
  seedRoster()
})
afterEach(() => {
  document.body.innerHTML = ''
})

describe('CommitteeManagement.vue gating (Note 11)', () => {
  it('shows the management controls when can(committee management.manage)', async () => {
    canManage = true
    const wrapper = mountView()
    await flushPromises()
    const text = wrapper.text()
    expect(text).toContain('Add an assessor')
    expect(text).toContain('Rebalance assignments')
    expect(text).toContain('Remove')
    expect(wrapper.find('.committee-management__gated').exists()).toBe(false)
  })

  it('HIDES the management controls without can(committee management.manage)', async () => {
    canManage = false
    const wrapper = mountView()
    await flushPromises()
    const text = wrapper.text()
    expect(text).not.toContain('Add an assessor')
    expect(text).not.toContain('Rebalance assignments')
    expect(wrapper.find('.committee-management__gated').exists()).toBe(true)
    expect(text).not.toContain('Reassign an entity')
  })

  it('loads the roster on mount and lists each assessor with its assignment load', async () => {
    const wrapper = mountView()
    await flushPromises()
    expect(load).toHaveBeenCalled()
    expect(wrapper.text()).toContain('Assessor A')
    expect(wrapper.text()).toContain('2 assignments')
  })
})
