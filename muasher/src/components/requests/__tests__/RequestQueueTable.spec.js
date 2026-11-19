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
  it('puts a compact level badge beside the entity name, «بلا مستوى» without a level', () => {
    const wrapper = mount(RequestQueueTable, {
      props: {
        items: [ROW, { ...ROW, id: 9102, entity: { ...ROW.entity, id: 6003, level: null } }],
        status: 'success',
      },
      global: createTestGlobals(),
    })
    const badges = wrapper.findAll('.request-queue__entity .level-badge--s')
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
    const badge = wrapper.find('.request-queue__entity .level-badge')
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
