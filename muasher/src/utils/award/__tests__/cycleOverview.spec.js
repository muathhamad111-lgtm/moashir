import { describe, expect, it } from 'vitest'
import { detailGroups, overviewFacts } from '@/utils/award/cycleOverview'
import { summarySections } from '@/utils/award/cycleSummary'

const t = (key, values) => (values ? `${key}:${JSON.stringify(values)}` : key)

const format = {
  t,
  plain: (value) => (value === null || value === undefined ? '—' : String(value)),
  date: (value) => value ?? '—',
  regionsTotal: 13,
  text: (value) => value?.en ?? '—',
  window: (start, end) => `${start} → ${end}`,
  flag: (value) => (value ? 'on' : 'off'),
  list: (names) => names.join(', ') || '—',
}

const CYCLE = {
  id: 31,
  name: { ar: 'دورة 2026', en: 'Cycle 2026' },
  description: { ar: 'تعريف الدورة', en: 'About the cycle' },
  year: 2026,
  status: 'submission-open',
  revealsStaff: true,
  submissionStart: '2026-10-01',
  submissionEnd: '2026-12-15',
  committeeStart: '2027-01-04',
  committeeEnd: '2027-02-15',
  announcementAt: '2027-03-01',
  conditions: { minEntityAgeYears: 3, disqualificationScore: '60.00' },
  initialReviewSlaDays: 14,
  participationCertificateEnabled: true,
  entryFee: '0.00',
  regions: [
    { id: 1, name: { ar: 'الرياض', en: 'Riyadh' } },
    { id: 2, name: { ar: 'مكة المكرمة', en: 'Makkah' } },
  ],
  members: [{ id: 18, name: 'أمين الجائزة', role: 'award_secretary' }],
  initialAssessors: [{ id: 10, name: 'مقيّم أولي', isActive: true }],
}

function groupsOf(cycle) {
  return detailGroups(summarySections(cycle, format), format)
}

function labelsOf(group) {
  return group.rows.map((row) => row.label)
}

describe('cycle overview facts', () => {
  it('puts the six key facts of the cycle first, in order', () => {
    const facts = overviewFacts(CYCLE, format)
    expect(facts.map((fact) => fact.key)).toEqual([
      'submission',
      'committee',
      'announcement',
      'regions',
      'fee',
      'threshold',
    ])
    expect(facts.every((fact) => fact.icon && fact.label)).toBe(true)
    const valueOf = (key) => facts.find((fact) => fact.key === key).value
    expect(valueOf('submission')).toBe('2026-10-01 → 2026-12-15')
    expect(valueOf('committee')).toBe('2027-01-04 → 2027-02-15')
    expect(valueOf('announcement')).toBe('2027-03-01')
    expect(valueOf('regions')).toBe('cycles.setup.regions.count:{"count":2,"total":13}')
    expect(valueOf('threshold')).toBe('60.00')
  })

  it('reads a free cycle as free and a paid one with its amount', () => {
    const feeOf = (cycle) => overviewFacts(cycle, format).find((fact) => fact.key === 'fee').value
    expect(feeOf(CYCLE)).toBe('cycles.setup.fee.free')
    expect(feeOf({ ...CYCLE, entryFee: null })).toBe('cycles.setup.fee.free')
    expect(feeOf({ ...CYCLE, entryFee: '450.00' })).toBe(
      'cycles.setup.fee.paidAmount:{"amount":"450.00"}',
    )
  })

  it('shows the empty mark for a date not set yet', () => {
    const facts = overviewFacts({ ...CYCLE, announcementAt: null }, format)
    expect(facts.find((fact) => fact.key === 'announcement').value).toBe('—')
  })
})

describe('cycle detail groups', () => {
  it('groups the identity, the conditions with the deadlines, and the team', () => {
    const groups = groupsOf(CYCLE)
    expect(groups.map((group) => group.key)).toEqual(['identity', 'conditions', 'team'])
    expect(groups.map((group) => group.title)).toEqual([
      'cycles.setup.steps.identity',
      'cycles.detail.conditions',
      'cycles.setup.steps.team',
    ])
  })

  it('shows the description as the identity text and the short facts as its rows', () => {
    const identity = groupsOf(CYCLE).find((group) => group.key === 'identity')
    expect(identity.text).toBe('About the cycle')
    expect(labelsOf(identity)).toEqual([
      'cycles.form.nameAr',
      'cycles.form.nameEn',
      'cycles.form.year',
    ])
  })

  it('leaves the threshold out of the conditions, as it is already among the key facts', () => {
    const conditions = groupsOf(CYCLE).find((group) => group.key === 'conditions')
    expect(labelsOf(conditions)).not.toContain('cycles.form.disqualificationScore')
    expect(labelsOf(conditions)).toEqual([
      'cycles.form.regions',
      'cycles.form.minEntityAgeYears',
      'cycles.form.participationCertificate',
      'cycles.form.initialReviewSlaDays',
    ])
    expect(conditions.rows[0].value).toContain('Riyadh, Makkah')
  })

  it('never repeats the dates or the fee of the key facts', () => {
    const labels = groupsOf(CYCLE).flatMap(labelsOf)
    expect(labels).not.toContain('cycles.dates.submission')
    expect(labels).not.toContain('cycles.dates.committee')
    expect(labels).not.toContain('cycles.dates.announcement')
    expect(labels).not.toContain('cycles.setup.fee.label')
  })

  it('shows the committee assessors in the team card, or that none is appointed yet', () => {
    const teamWith = (committee) =>
      detailGroups(summarySections(CYCLE, format, committee), format).find(
        (group) => group.key === 'team',
      )
    expect(teamWith(['David Nguyen']).rows.at(-1)).toMatchObject({
      label: 'cycles.staff.committee.label',
      value: 'David Nguyen',
    })
    expect(teamWith([]).rows.at(-1).value).toBe('cycles.staff.committee.none')
    expect(labelsOf(teamWith(null))).not.toContain('cycles.staff.committee.label')
  })

  it('adds the team only for a reader who sees it', () => {
    const team = groupsOf(CYCLE).find((group) => group.key === 'team')
    expect(team.rows.find((row) => row.label.includes('award_secretary')).value).toBe(
      'أمين الجائزة',
    )
    expect(team.rows.at(-1)).toMatchObject({ value: 'مقيّم أولي', missing: false })
    expect(groupsOf({ ...CYCLE, revealsStaff: false }).map((group) => group.key)).toEqual([
      'identity',
      'conditions',
    ])
  })
})
