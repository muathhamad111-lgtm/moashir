export const DAY_MS = 24 * 60 * 60 * 1000

export function addDays(iso, days) {
  return new Date(new Date(iso).getTime() + days * DAY_MS).toISOString()
}
