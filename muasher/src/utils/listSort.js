// A list's sort as the API reads it: a column, "-" in front for descending.
const DESCENDING = '-'

export const SORT_DIRECTIONS = Object.freeze({ ASC: 'asc', DESC: 'desc' })

export function sortDirectionOf(sort, key) {
  if (sort === key) return SORT_DIRECTIONS.ASC
  return sort === `${DESCENDING}${key}` ? SORT_DIRECTIONS.DESC : null
}

// Clicking a column header: the same column flips direction; another column starts in its own
// first direction (newest first suits a date of assignment, soonest first a due date).
export function nextSort(sort, key, firstDirection = SORT_DIRECTIONS.ASC) {
  const current = sortDirectionOf(sort, key)
  const direction = current
    ? current === SORT_DIRECTIONS.ASC
      ? SORT_DIRECTIONS.DESC
      : SORT_DIRECTIONS.ASC
    : firstDirection
  return direction === SORT_DIRECTIONS.DESC ? `${DESCENDING}${key}` : key
}
