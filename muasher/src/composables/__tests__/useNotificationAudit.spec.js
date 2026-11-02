import { describe, expect, it, vi, beforeEach } from 'vitest'
import { useNotificationAudit } from '@/composables/useNotificationAudit'
import { notificationsService } from '@/services/notificationsService'

vi.mock('@/services/notificationsService', () => ({
  notificationsService: { getAudit: vi.fn() },
}))

const entries = [
  {
    id: 'aud-1',
    recipient: { ar: 'الجهة', en: 'Entity' },
    recipientRole: 'entity',
    channel: 'email',
    receivedAt: '2026-07-06T21:05:00+03:00',
    openedAt: '2026-07-06T21:18:00+03:00',
    actionedAt: null,
  },
]

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useNotificationAudit', () => {
  it('starts idle before any load', () => {
    const { status } = useNotificationAudit()
    expect(status.value).toBe('idle')
  })

  it('loads audit entries and resolves success with the notification meta', async () => {
    notificationsService.getAudit.mockResolvedValue({
      data: { code: 'PAY-02', link: '/x', entries },
      meta: {},
      errors: null,
    })
    const audit = useNotificationAudit()
    await audit.load(8)
    expect(notificationsService.getAudit).toHaveBeenCalledWith(8)
    expect(audit.status.value).toBe('success')
    expect(audit.entries.value).toHaveLength(1)
    expect(audit.meta.value.code).toBe('PAY-02')
  })

  it('resolves the empty state when there are no entries', async () => {
    notificationsService.getAudit.mockResolvedValue({
      data: { code: 'REG-01', link: '/x', entries: [] },
      meta: {},
      errors: null,
    })
    const audit = useNotificationAudit()
    await audit.load(1)
    expect(audit.status.value).toBe('empty')
  })

  it('reports the error state and clears entries when the service fails', async () => {
    notificationsService.getAudit.mockRejectedValue(new Error('boom'))
    const audit = useNotificationAudit()
    await audit.load(9)
    expect(audit.status.value).toBe('error')
    expect(audit.entries.value).toEqual([])
  })

  it('resets back to idle', async () => {
    notificationsService.getAudit.mockResolvedValue({
      data: { code: 'PAY-02', link: '/x', entries },
      meta: {},
      errors: null,
    })
    const audit = useNotificationAudit()
    await audit.load(8)
    audit.reset()
    expect(audit.status.value).toBe('idle')
    expect(audit.entries.value).toEqual([])
  })
})
