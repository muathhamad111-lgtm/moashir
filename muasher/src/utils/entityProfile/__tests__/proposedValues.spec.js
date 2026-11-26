import { describe, expect, it } from 'vitest'
import { pendingSectionsOf, withProposedValues } from '@/utils/entityProfile/proposedValues'

const PENDING = {
  changes: [
    { item: 'employees_count', kind: 'field', section: 'governance', newValue: 77 },
    { item: 'city_id', kind: 'field', section: 'classification', newValue: { id: 9, code: 'jed' } },
    { item: 'logo', kind: 'file', section: null, newValue: { id: 'f-1' } },
    { item: 'vat_certificate', kind: 'file', section: 'billing', newValue: null, removes: true },
  ],
}

describe('proposed profile values', () => {
  it('shows each pending field with its proposed value', () => {
    const profile = { employeesCount: 12, city: { id: 3 }, websiteUrl: 'https://a.sa' }
    expect(withProposedValues(profile, PENDING)).toEqual({
      employeesCount: 77,
      city: { id: 9, code: 'jed' },
      websiteUrl: 'https://a.sa',
    })
    expect(profile.employeesCount).toBe(12)
  })

  it('leaves the profile as it is without a pending request', () => {
    const profile = { employeesCount: 12 }
    expect(withProposedValues(profile, null)).toBe(profile)
  })

  it('names the sections that hold a pending change', () => {
    expect([...pendingSectionsOf(PENDING)].sort()).toEqual([
      'billing',
      'classification',
      'governance',
    ])
  })
})
