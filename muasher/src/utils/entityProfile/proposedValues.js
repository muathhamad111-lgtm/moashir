import { toCamelKey } from '@/api/caseMapping'

const PLACE_ITEMS = { region_id: 'region', city_id: 'city' }

/** The section a pending change item belongs to, files included. */
export function sectionOfItem(change) {
  return change.section ?? null
}

/** The profile as the entity proposed it: every pending field shows its proposed value. */
export function withProposedValues(profile, pendingChange) {
  const rows = (pendingChange?.changes ?? []).filter((change) => change.kind === 'field')
  if (!profile || rows.length === 0) return profile
  const proposed = { ...profile }
  for (const row of rows) {
    const placeKey = PLACE_ITEMS[row.item]
    if (placeKey) proposed[placeKey] = row.newValue ? { ...row.newValue } : null
    else proposed[toCamelKey(row.item)] = row.newValue
  }
  return proposed
}

/** The keys of the profile sections that hold a pending change. */
export function pendingSectionsOf(pendingChange) {
  return new Set((pendingChange?.changes ?? []).map(sectionOfItem).filter(Boolean))
}
