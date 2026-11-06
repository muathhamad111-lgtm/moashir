import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import EntityTable from '@/components/entities/admin/EntityTable.vue'

const ROW = {
  id: 6002,
  reference: 'ENT-006002',
  name: { ar: 'جمعية إحسان الخيرية', en: 'Ehsan Charitable Society' },
  entityType: 'civil_association',
  status: 'active',
  createdAt: '2026-01-10T08:00:00Z',
  level: 'gold',
  levelCertificateNumber: 'CRT-2026-000010',
  hasOpenAccreditedRequest: false,
}

function render(items, { full = true, locale = 'ar' } = {}) {
  return mount(EntityTable, {
    props: { items, status: 'success', full, emptyTitle: 'empty' },
    global: createTestGlobals({ locale }),
  })
}

function tiers(wrapper) {
  return wrapper.findAll('.level-badge').map((badge) => badge.attributes('data-tier'))
}

describe('EntityTable level column', () => {
  it('shows a compact level badge per row in the full and contact variants', () => {
    for (const full of [true, false]) {
      const wrapper = render([ROW], { full })
      expect(wrapper.text()).toContain('المستوى')
      expect(wrapper.find('.level-badge--s').text()).toContain('ذهبي')
    }
  })

  it('maps the server level, the open request flag and the re-assessment chip', () => {
    const wrapper = render([
      ROW,
      { ...ROW, id: 1, level: null, hasOpenAccreditedRequest: true },
      { ...ROW, id: 2, level: null },
      { ...ROW, id: 3, level: 'participation' },
      { ...ROW, id: 4, level: 'silver', hasOpenAccreditedRequest: true },
    ])
    expect(tiers(wrapper)).toEqual(['gold', 'progress', 'none', 'participation', 'silver'])
    expect(wrapper.findAll('.level-badge__chip')).toHaveLength(1)
  })

  it('keeps the deleted variant without a level column', () => {
    const wrapper = mount(EntityTable, {
      props: { items: [ROW], status: 'success', deleted: true, emptyTitle: 'empty' },
      global: createTestGlobals({ locale: 'en' }),
    })
    expect(wrapper.find('.level-badge').exists()).toBe(false)
  })
})
