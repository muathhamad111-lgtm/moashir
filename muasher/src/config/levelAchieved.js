const ISO_DATE = /^(\d{4})-(\d{2})-(\d{2})$/
const RELEASE_UTC_OFFSET = '+03:00'
const SETTING_NAME = 'VITE_LEVEL_ACHIEVED_SINCE'

function isCalendarDate(year, month, day) {
  const date = new Date(Date.UTC(year, month - 1, day))
  return (
    date.getUTCFullYear() === year && date.getUTCMonth() === month - 1 && date.getUTCDate() === day
  )
}

export function parseLevelAchievedSince(value) {
  const match = ISO_DATE.exec(typeof value === 'string' ? value.trim() : '')
  if (!match) return null
  const [, year, month, day] = match.map(Number)
  if (!isCalendarDate(year, month, day)) return null
  return Date.parse(`${match[0]}T00:00:00${RELEASE_UTC_OFFSET}`)
}

export function warnInDevelopment(value) {
  if (!import.meta.env.DEV || import.meta.env.MODE === 'test') return
  console.warn(
    `${SETTING_NAME} is missing or not an ISO 8601 date (YYYY-MM-DD): "${value ?? ''}". The level-achieved dialog is disabled.`,
  )
}

export function resolveLevelAchievedSince(value, onInvalid = warnInDevelopment) {
  const since = parseLevelAchievedSince(value)
  if (since === null) onInvalid(value)
  return since
}

export function isIssuedSince(issuedAt, since) {
  if (since === null) return false
  const issued = Date.parse(issuedAt ?? '')
  return Number.isFinite(issued) && issued >= since
}

export const LEVEL_ACHIEVED_SINCE = resolveLevelAchievedSince(
  import.meta.env.VITE_LEVEL_ACHIEVED_SINCE,
)
