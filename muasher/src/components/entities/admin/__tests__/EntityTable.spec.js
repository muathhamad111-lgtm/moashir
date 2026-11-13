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

describe('EntityTable last level (LC-5)', () => {
  const lastLevel = (validity, daysUntilExpiry = null) => ({
    level: 'gold',
    validUntil: '2026-11-23',
    validity,
    daysUntilExpiry,
  })

  it('shows the expired last level muted with its chip instead of «بلا مستوى»', () => {
    const wrapper = render([{ ...ROW, level: null, lastLevel: lastLevel('expired') }])
    const badge = wrapper.find('.level-badge')
    expect(badge.attributes('data-tier')).toBe('gold')
    expect(badge.classes()).toContain('level-badge--expired')
    expect(wrapper.find('[data-testid="level-validity"]').text()).toContain('منتهية')
  })

  it('adds the expiring chip and leaves a valid level as the badge alone', () => {
    const wrapper = render(
      [
        { ...ROW, lastLevel: lastLevel('expiring_soon', 18) },
        { ...ROW, id: 2, lastLevel: lastLevel('valid', 120) },
        { ...ROW, id: 3, level: null, lastLevel: null },
      ],
      { locale: 'en' },
    )
    expect(tiers(wrapper)).toEqual(['gold', 'gold', 'none'])
    const chips = wrapper.findAll('[data-testid="level-validity"]')
    expect(chips.map((chip) => chip.attributes('data-validity'))).toEqual(['expiring_soon'])
  })

  it('keeps the re-assessment chip beside an expired metal level with an open request', () => {
    const wrapper = render([
      { ...ROW, level: null, hasOpenAccreditedRequest: true, lastLevel: lastLevel('expired') },
    ])
    expect(tiers(wrapper)).toEqual(['gold'])
    expect(wrapper.find('.level-badge__chip').exists()).toBe(true)
  })
})
