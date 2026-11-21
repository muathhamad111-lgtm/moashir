import { describe, expect, it } from 'vitest'
import { groupNotificationsByDay, notificationGroupOf } from '@/utils/notificationGroups'

const NOW = new Date('2026-11-04T10:00:00+03:00')

describe('notification day groups (Asia/Riyadh)', () => {
  it('places items in today, this week and older', () => {
    expect(notificationGroupOf('2026-11-04T00:30:00+03:00', NOW)).toBe('today')
    expect(notificationGroupOf('2026-11-03T23:59:00+03:00', NOW)).toBe('week')
    expect(notificationGroupOf('2026-10-29T08:00:00+03:00', NOW)).toBe('week')
    expect(notificationGroupOf('2026-10-28T08:00:00+03:00', NOW)).toBe('older')
  })

  it('uses the Riyadh day, not the UTC day', () => {
    expect(notificationGroupOf('2026-11-03T21:30:00Z', NOW)).toBe('today')
  })

  it('keeps the order inside groups and drops empty groups', () => {
    const items = [
      { id: 'a', createdAt: '2026-11-04T08:00:00+03:00' },
      { id: 'b', createdAt: '2026-10-01T08:00:00+03:00' },
      { id: 'c', createdAt: '2026-11-04T07:00:00+03:00' },
    ]
    expect(groupNotificationsByDay(items, NOW)).toEqual([
      { key: 'today', items: [items[0], items[2]] },
      { key: 'older', items: [items[1]] },
    ])
  })
})
