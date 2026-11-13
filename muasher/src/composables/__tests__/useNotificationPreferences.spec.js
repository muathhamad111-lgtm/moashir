import { describe, expect, it, vi, beforeEach } from 'vitest'
import { withSetup } from '@/test-utils/withSetup'
import { useNotificationPreferences } from '@/composables/useNotificationPreferences'
import { notificationsService } from '@/services/notificationsService'

vi.mock('@/services/notificationsService', () => ({
  notificationsService: { getPreferences: vi.fn(), updatePreferences: vi.fn() },
}))

function row(code, isMandatory, channels) {
  return { code, module: code.split('-')[0], category: 'informational', isMandatory, channels }
}

const ROWS = [
  row('ACC-01', false, [
    { channel: 'in_app', enabled: true, configurable: false },
    { channel: 'email', enabled: true, configurable: true },
  ]),
  row('PAY-02', true, [
    { channel: 'in_app', enabled: true, configurable: false },
    { channel: 'email', enabled: true, configurable: false },
  ]),
  row('SELF-01', false, [{ channel: 'in_app', enabled: true, configurable: false }]),
]

beforeEach(() => {
  vi.clearAllMocks()
  notificationsService.getPreferences.mockResolvedValue({ data: ROWS })
  notificationsService.updatePreferences.mockImplementation((changes) =>
    Promise.resolve({
      data: ROWS.map((candidate) =>
        candidate.code === 'ACC-01'
          ? {
              ...candidate,
              channels: candidate.channels.map((channel) =>
                channel.channel === 'email' ? { ...channel, enabled: changes[0].enabled } : channel,
              ),
            }
          : candidate,
      ),
    }),
  )
})

describe('useNotificationPreferences', () => {
  it('lists only types with an e-mail or SMS channel, grouped by module', async () => {
    const { api } = withSetup(() => useNotificationPreferences())
    await api.load()
    expect(api.status.value).toBe('success')
    expect(api.groups.value.map((group) => group.module)).toEqual(['ACC', 'PAY'])
    expect(api.isEnabled('PAY-02', 'email')).toBe(true)
  })

  it('sends only the changed configurable channels', async () => {
    const { api } = withSetup(() => useNotificationPreferences())
    await api.load()
    expect(await api.save()).toBe(true)
    expect(notificationsService.updatePreferences).not.toHaveBeenCalled()
    api.setEnabled('ACC-01', 'email', false)
    expect(api.changes.value).toEqual([{ code: 'ACC-01', channel: 'email', enabled: false }])
    expect(await api.save()).toBe(true)
    expect(notificationsService.updatePreferences).toHaveBeenCalledWith([
      { code: 'ACC-01', channel: 'email', enabled: false },
    ])
    expect(api.changes.value).toEqual([])
  })

  it('keeps the draft and exposes the API message when the save is refused', async () => {
    notificationsService.updatePreferences.mockRejectedValueOnce({
      normalized: { message: 'This notification is mandatory and cannot be turned off.' },
    })
    const { api } = withSetup(() => useNotificationPreferences())
    await api.load()
    api.setEnabled('ACC-01', 'email', false)
    expect(await api.save()).toBe(false)
    expect(api.errorMessage.value).toMatch(/mandatory/)
    expect(api.changes.value).toHaveLength(1)
  })
})
