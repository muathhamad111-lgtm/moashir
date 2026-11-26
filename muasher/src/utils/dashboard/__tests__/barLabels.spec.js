import { describe, expect, it } from 'vitest'
import { barLabelOf, barRows } from '@/utils/dashboard/barLabels'
import { BAR_LABELS, DASHBOARD_WIDGETS, widgetDefinition } from '@/constants/dashboardWidgets'

const messages = {
  'dashboard.bars.notReported': 'Not reported',
  'dashboard.bars.bands.below_50': 'Below 50',
  'entities.enums.developmentSector.social': 'Social',
  'finance.purposes.accredited': 'Accredited assessment',
  'paths.labels.accredited': 'Accredited assessment',
}
const i18n = {
  t: (key, params) =>
    key === 'dashboard.bars.decade' ? `${params.from}–${params.to}` : (messages[key] ?? key),
  te: (key) => key in messages,
  locale: 'en',
}

describe('dashboard bar labels', () => {
  it.each([
    [BAR_LABELS.NAME, { name: { ar: 'الرياض', en: 'Riyadh' } }, 'Riyadh'],
    [BAR_LABELS.PATH, { path: 'accredited', pathLabel: null }, 'Accredited assessment'],
    [
      BAR_LABELS.PATH,
      { path: 'accredited', pathLabel: { ar: 'م', en: 'Served label' } },
      'Served label',
    ],
    [BAR_LABELS.SECTOR, { key: 'social' }, 'Social'],
    [BAR_LABELS.SECTOR, { key: '' }, 'Not reported'],
    [BAR_LABELS.DECADE, { key: '2010' }, '2010–2019'],
    [BAR_LABELS.DECADE, { key: null }, 'Not reported'],
    [BAR_LABELS.BAND, { key: 'below_50' }, 'Below 50'],
    [BAR_LABELS.PURPOSE, { purpose: 'accredited' }, 'Accredited assessment'],
    [BAR_LABELS.PURPOSE, { purpose: 'future' }, 'future'],
  ])('labels a %s bar', (kind, item, expected) => {
    expect(barLabelOf(kind, item, i18n)).toBe(expected)
  })

  it('scales the bars against the largest count and keeps the amounts', () => {
    const rows = barRows(
      DASHBOARD_WIDGETS.by_service,
      [
        { purpose: 'accredited', count: 4, total: '4600.00' },
        { purpose: 'bag', count: 2, total: '800.00' },
      ],
      i18n,
    )
    expect(rows.map((row) => [row.count, row.ratio, row.amount])).toEqual([
      [4, 100, '4600.00'],
      [2, 50, '800.00'],
    ])
    expect(barRows(DASHBOARD_WIDGETS.entities_by_sector, null, i18n)).toEqual([])
  })

  it('uses the board-specific cycles table and ignores unknown widgets', () => {
    expect(widgetDefinition('committee_assessor', 'cycles').columns.map((c) => c.key)).toContain(
      'remaining',
    )
    expect(widgetDefinition('award_secretary', 'cycles').columns.map((c) => c.key)).toContain(
      'submissionEnd',
    )
    expect(widgetDefinition('system_admin', 'nothing')).toBeNull()
  })
})
