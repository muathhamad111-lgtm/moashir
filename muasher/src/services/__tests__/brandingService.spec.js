import { describe, expect, it, vi, beforeEach } from 'vitest'
import { brandingService } from '@/services/brandingService'
import { profileService } from '@/services/profileService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const resource = {
  accentToken: 'teal',
  logoLight: { fileId: 6, url: 'data:image/png;base64,LIGHT' },
  logoDark: { fileId: 7, url: 'data:image/png;base64,AAAA' },
  palette: [{ token: 'teal', hex: '#33d1c4', textOnLightHex: '#0b7068', label: { ar: 'فيروزي', en: 'Teal' } }],
  updatedAt: '2026-10-31T09:00:00Z',
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('brandingService', () => {
  it('maps the snake_case theme resource to the SPA shape', async () => {
    apiClient.get.mockResolvedValue({ data: { data: resource, meta: {}, errors: null } })
    const { data } = await brandingService.get()
    expect(apiClient.get).toHaveBeenCalledWith('/settings/branding')
    expect(data).toEqual({
      accentToken: 'teal',
      logos: { dark: 'data:image/png;base64,LIGHT', light: 'data:image/png;base64,AAAA' },
      palette: [{ token: 'teal', hex: '#33d1c4', textOnLightHex: '#0b7068', label: { ar: 'فيروزي', en: 'Teal' } }],
      updatedAt: '2026-10-31T09:00:00Z',
    })
  })

  it('sends the accent as a snake_case token id only', async () => {
    apiClient.put.mockResolvedValue({ data: { data: resource, meta: {}, errors: null } })
    await brandingService.update({ accentToken: 'amber' })
    expect(apiClient.put).toHaveBeenCalledWith('/settings/branding', { accentToken: 'amber' })
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'branding.update',
      expect.objectContaining({ payloadShape: ['accentToken'] }),
    )
  })

  it('uploads the dark-background logo as the light variant (multipart)', async () => {
    apiClient.post.mockResolvedValue({ data: { data: { fileId: 9, url: 'blob:logo' }, meta: {}, errors: null } })
    const file = new File(['logo'], 'logo.png', { type: 'image/png' })
    const { data } = await brandingService.uploadLogo(file, 'dark')
    const [url, form] = apiClient.post.mock.calls[0]
    expect(url).toBe('/settings/branding/logo')
    expect(form.get('variant')).toBe('light')
    expect(form.get('file')).toBeInstanceOf(File)
    expect(data).toEqual({ fileId: 9, url: 'blob:logo' })
  })

  it('uploads the light-background logo as the dark variant', async () => {
    apiClient.post.mockResolvedValue({ data: { data: { fileId: 10, url: 'blob:logo' }, meta: {}, errors: null } })
    await brandingService.uploadLogo(new File(['logo'], 'logo.svg', { type: 'image/svg+xml' }), 'light')
    expect(apiClient.post.mock.calls[0][1].get('variant')).toBe('dark')
  })

  it('resets the accent and both logos through the single global reset verb', async () => {
    apiClient.post.mockResolvedValue({
      data: { data: { ...resource, accentToken: 'brand_default', logoLight: null, logoDark: null }, meta: {}, errors: null },
    })
    const { data } = await brandingService.reset()
    expect(apiClient.post).toHaveBeenCalledTimes(1)
    expect(apiClient.post).toHaveBeenCalledWith('/settings/branding/reset')
    expect(data.accentToken).toBe('brand_default')
    expect(data.logos).toEqual({ dark: null, light: null })
    expect(brandingService).not.toHaveProperty('resetLogo')
  })

  it('reports failures through the logger and rethrows', async () => {
    const failure = new Error('forbidden')
    apiClient.put.mockRejectedValue(failure)
    await expect(brandingService.update({ accentToken: 'teal' })).rejects.toBe(failure)
    expect(logger.error).toHaveBeenCalledWith(failure, expect.objectContaining({ action: 'branding.update' }))
  })
})

describe('profileService', () => {
  it('loads the signed-in user roles and permissions', async () => {
    apiClient.get.mockResolvedValue({
      data: {
        data: {
          roles: [{ key: 'admin', label: { ar: 'مدير النظام', en: 'System Administrator' } }],
          permissions: [{ key: 'settings.branding.manage', module: 'settings', label: { ar: 'الهوية', en: 'Identity' } }],
        },
        meta: {},
        errors: null,
      },
    })
    const { data } = await profileService.getMyPermissions()
    expect(apiClient.get).toHaveBeenCalledWith('/me/profile/permissions')
    expect(data.roles[0].key).toBe('admin')
    expect(data.permissions[0]).toEqual({ key: 'settings.branding.manage', module: 'settings', label: { ar: 'الهوية', en: 'Identity' } })
  })

  it('tolerates an empty payload', async () => {
    apiClient.get.mockResolvedValue({ data: { data: null, meta: {}, errors: null } })
    const { data } = await profileService.getMyPermissions()
    expect(data).toEqual({ roles: [], permissions: [] })
  })
})
