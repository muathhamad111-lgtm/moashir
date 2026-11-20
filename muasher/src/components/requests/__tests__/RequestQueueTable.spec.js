import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import RequestQueueTable from '@/components/requests/RequestQueueTable.vue'

const ROW = {
  id: 9101,
  number: 'ACC-2026-000058',
  path: 'accredited',
  pathLabel: null,
  status: 'submitted',
  entity: { id: 6002, reference: 'ENT-006002', name: { ar: 'جمعية إحسان' }, level: 'gold' },
  assessor: null,
  submittedAt: '2026-11-01T08:00:00Z',
  dueAt: null,
  isOverdue: false,
}

describe('RequestQueueTable entity level', () => {
  it('puts a compact level badge under the entity name in the request column, «بلا مستوى» without a level', () => {
    const wrapper = mount(RequestQueueTable, {
      props: {
        items: [ROW, { ...ROW, id: 9102, entity: { ...ROW.entity, id: 6003, level: null } }],
        status: 'success',
      },
      global: createTestGlobals(),
    })
    const badges = wrapper.findAll('.request-queue__request .level-badge--s')
    expect(badges.map((badge) => badge.attributes('data-tier'))).toEqual(['gold', 'none'])
    expect(badges[1].text()).toContain('بلا مستوى')
  })
})

describe('RequestQueueTable last level (LC-5)', () => {
  it('shows the expired last level of the entity muted with its chip', () => {
    const wrapper = mount(RequestQueueTable, {
      props: {
        items: [
          {
            ...ROW,
            entity: {
              ...ROW.entity,
              level: null,
              lastLevel: {
                level: 'silver',
                validUntil: '2026-11-01',
                validity: 'expired',
                daysUntilExpiry: null,
              },
            },
          },
        ],
        status: 'success',
      },
      global: createTestGlobals({ locale: 'en' }),
    })
    const badge = wrapper.find('.request-queue__request .level-badge')
    expect(badge.attributes('data-tier')).toBe('silver')
    expect(badge.classes()).toContain('level-badge--expired')
    expect(wrapper.find('[data-testid="level-validity"]').text()).toContain('Expired')
  })
})

describe('RequestQueueTable row actions', () => {
  function mountTable(items, canAssign) {
    return mount(RequestQueueTable, {
      props: { items, status: 'success', canAssign },
      global: createTestGlobals({ locale: 'en' }),
    })
  }

  const ASSIGNED = {
    ...ROW,
    id: 9103,
    number: 'ACC-2026-000060',
    status: 'under-review',
    assessor: { id: 7001, name: 'Sultan Al-Qarni' },
  }
  const FROZEN = { ...ROW, id: 9104, number: 'ACC-2026-000061', isFrozen: true }
  const COMPLETED = { ...ASSIGNED, id: 9105, number: 'ACC-2026-000062', status: 'completed' }

  it('offers «Assign» on an unassigned row and «Reassign» on one in progress, and emits the mode', async () => {
    const wrapper = mountTable([ROW, ASSIGNED, FROZEN, COMPLETED], true)
    const buttons = wrapper.findAll('[data-testid="queue-row-assign"]')
    expect(buttons.map((button) => button.text())).toEqual(['Assign', 'Reassign'])
    expect(buttons[0].attributes('aria-label')).toBe(
      'Assign request ACC-2026-000058 to an assessor',
    )
    expect(buttons[0].classes()).toContain('btn--primary')
    await buttons[1].trigger('click')
    expect(wrapper.emitted('assign')).toEqual([[{ item: ASSIGNED, mode: 'reassign' }]])
  })

  it('shows only the view link to a user who cannot assign', () => {
    const wrapper = mountTable([ROW, ASSIGNED], false)
    expect(wrapper.findAll('[data-testid="queue-row-assign"]')).toHaveLength(0)
    const views = wrapper.findAll('.table__cell--actions a')
    expect(views.map((link) => link.attributes('aria-label'))).toEqual([
      'View request ACC-2026-000058',
      'View request ACC-2026-000060',
    ])
  })
})

// Admin review 2026-11-20 (R1).
describe('RequestQueueTable merged request column, draft progress and assessor', () => {
  function mountTable(items, locale = 'ar') {
    return mount(RequestQueueTable, {
      props: { items, status: 'success' },
      global: createTestGlobals({ locale }),
    })
  }

  it('shows the number, the entity name and its level in one request column', () => {
    const wrapper = mountTable([ROW])
    const headers = wrapper.findAll('th').map((cell) => cell.text())
    expect(headers).not.toContain('الجهة')
    const cell = wrapper.find('.request-queue__request')
    expect(cell.find('a[href="/admin/requests/9101"]').text()).toBe('ACC-2026-000058')
    expect(cell.find('.request-queue__entity-name').text()).toBe('جمعية إحسان')
    expect(cell.find('.level-badge').exists()).toBe(true)
  })

  it('shows a draft as being prepared with the entity progress and no assessor yet', () => {
    const draft = { ...ROW, status: 'draft', progress: { complete: 3, total: 19 } }
    const wrapper = mountTable([draft], 'en')
    expect(wrapper.find('.request-queue__preparing').text()).toContain('Being prepared')
    expect(wrapper.find('[role="progressbar"]').attributes('aria-valuenow')).toBe('3')
    expect(wrapper.text()).not.toContain('Unassigned')
  })

  it('keeps the assessor of a completed request and marks a reassigned one', () => {
    const completed = {
      ...ROW,
      status: 'completed',
      assessor: { id: 7001, name: 'د. سلطان القرني' },
      isReassigned: false,
    }
    const moved = {
      ...ROW,
      id: 9102,
      status: 'under-review',
      assessor: { id: 7002, name: 'أ. هند الزهراني' },
      isReassigned: true,
    }
    const wrapper = mountTable([completed, moved])
    const cells = wrapper.findAll('.request-queue__assessor')
    expect(cells[0].text()).toBe('د. سلطان القرني')
    expect(cells[1].text()).toContain('أ. هند الزهراني')
    expect(cells[1].text()).toContain('أُعيد الإسناد')
  })
})
