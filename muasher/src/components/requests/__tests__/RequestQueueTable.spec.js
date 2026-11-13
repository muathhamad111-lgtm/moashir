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
