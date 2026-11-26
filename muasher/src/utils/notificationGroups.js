import { riyadhDateKey } from '@/utils/datetime'

export const NOTIFICATION_GROUPS = Object.freeze(['today', 'week', 'older'])

const DAY_MS = 24 * 60 * 60 * 1000
const WEEK_DAYS = 7

function daysBetween(fromKey, toKey) {
  return Math.round((Date.parse(toKey) - Date.parse(fromKey)) / DAY_MS)
}

export function notificationGroupOf(createdAt, now = new Date()) {
  const days = daysBetween(riyadhDateKey(createdAt), riyadhDateKey(now))
  if (days <= 0) return 'today'
  return days < WEEK_DAYS ? 'week' : 'older'
}

export function groupNotificationsByDay(items, now = new Date()) {
  return NOTIFICATION_GROUPS.map((key) => ({
    key,
    items: items.filter((item) => notificationGroupOf(item.createdAt, now) === key),
  })).filter((group) => group.items.length > 0)
}
