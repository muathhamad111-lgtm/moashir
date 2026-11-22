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

const scheme = {
  primary: '#d4088c',
  secondary: '#4a2a7d',
  onPrimary: '#ffffff',
  primaryText: '#c90885',
  primaryHover: '#bc097d',
  primarySoft: '#fbe6f4',
  onSecondary: '#ffffff',
}

const resource = {
  primaryColor: '#d4088c',
  secondaryColor: '#4a2a7d',
  colorOverrides: { onSecondary: '#ffffff', navbar: '#000000' },
  scheme,
  defaultColors: { primary: '#98d8d3', secondary: '#0a2325' },
  logoLight: { fileId: 6, url: 'data:image/png;base64,LIGHT' },
  logoDark: { fileId: 7, url: 'data:image/png;base64,AAAA' },
  updatedAt: '2026-10-31T09:00:00Z',
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('brandingService', () => {
  it('maps the snake_case theme resource to the SPA shape: sidebar logo, favicon and site logo', async () => {
    apiClient.get.mockResolvedValue({ data: { data: resource, meta: {}, errors: null } })
    const { data } = await brandingService.get()
    expect(apiClient.get).toHaveBeenCalledWith('/settings/branding')
    expect(data).toEqual({
      colors: { primary: '#d4088c', secondary: '#4a2a7d' },
      overrides: { onSecondary: '#ffffff' },
      scheme,
      defaultColors: { primary: '#98d8d3', secondary: '#0a2325' },
      logos: { dark: 'data:image/png;base64,LIGHT', favicon: null, site: null },
      updatedAt: '2026-10-31T09:00:00Z',
    })
  })

  it('sends the primary and secondary colours with the full set of overrides', async () => {
    apiClient.put.mockResolvedValue({ data: { data: resource, meta: {}, errors: null } })
    await brandingService.update({
      primaryColor: '#d4088c',
      secondaryColor: '#4a2a7d',
      colorOverrides: { primarySoft: '#fbe6f4' },
    })
    expect(apiClient.put).toHaveBeenCalledWith('/settings/branding', {
      primaryColor: '#d4088c',
      secondaryColor: '#4a2a7d',
      colorOverrides: { primarySoft: '#fbe6f4' },
    })
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'branding.update',
      expect.objectContaining({
        payloadShape: expect.arrayContaining(['primaryColor', 'secondaryColor']),
      }),
    )
  })

  it('uploads the dark-background logo as the light variant (multipart)', async () => {
    apiClient.post.mockResolvedValue({
      data: { data: { fileId: 9, url: 'blob:logo' }, meta: {}, errors: null },
    })
    const file = new File(['logo'], 'logo.png', { type: 'image/png' })
    const { data } = await brandingService.uploadLogo(file, 'dark')
    const [url, form] = apiClient.post.mock.calls[0]
    expect(url).toBe('/settings/branding/logo')
    expect(form.get('variant')).toBe('light')
    expect(form.get('file')).toBeInstanceOf(File)
    expect(data).toEqual({ fileId: 9, url: 'blob:logo' })
  })

  it('never uploads a light-background variant: any other surface falls back to the sidebar logo', async () => {
    apiClient.post.mockResolvedValue({
      data: { data: { fileId: 10, url: 'blob:logo' }, meta: {}, errors: null },
    })
    await brandingService.uploadLogo(
      new File(['logo'], 'logo.svg', { type: 'image/svg+xml' }),
      'light',
    )
    expect(apiClient.post.mock.calls[0][1].get('variant')).toBe('light')
  })

  it('resets the colours and the logo through the single global reset verb', async () => {
    apiClient.post.mockResolvedValue({
      data: {
        data: { ...resource, primaryColor: '#98d8d3', logoLight: null, logoDark: null },
        meta: {},
        errors: null,
      },
    })
    const { data } = await brandingService.reset()
    expect(apiClient.post).toHaveBeenCalledTimes(1)
    expect(apiClient.post).toHaveBeenCalledWith('/settings/branding/reset', {})
    expect(data.colors.primary).toBe('#98d8d3')
    expect(data.logos).toEqual({ dark: null, favicon: null, site: null })
    expect(brandingService).not.toHaveProperty('resetLogo')
  })

  it('sends the Super Admin reason with the colours, the reset and each logo', async () => {
    apiClient.put.mockResolvedValue({ data: { data: resource, meta: {}, errors: null } })
    apiClient.post.mockResolvedValue({ data: { data: resource, meta: {}, errors: null } })
    const reason = 'Aligning the back office with the approved identity'
    await brandingService.update({ primaryColor: '#d4088c', secondaryColor: '#4a2a7d', reason })
    await brandingService.reset({ reason })
    await brandingService.uploadLogo(
      new File(['logo'], 'logo.png', { type: 'image/png' }),
      'dark',
      reason,
    )
    expect(apiClient.put).toHaveBeenCalledWith('/settings/branding', {
      primaryColor: '#d4088c',
      secondaryColor: '#4a2a7d',
      colorOverrides: {},
      reason,
    })
    expect(apiClient.post).toHaveBeenNthCalledWith(1, '/settings/branding/reset', { reason })
    expect(apiClient.post.mock.calls[1][1].get('reason')).toBe(reason)
  })

  it('treats the contrast rejection as an expected answer', async () => {
    const rejected = Object.assign(new Error('contrast'), { response: { status: 422 } })
    apiClient.put.mockRejectedValue(rejected)
    await expect(
      brandingService.update({ primaryColor: '#777777', secondaryColor: '#0a2325' }),
    ).rejects.toBe(rejected)
    expect(logger.error).not.toHaveBeenCalled()
  })

  it('reports failures through the logger and rethrows', async () => {
    const failure = Object.assign(new Error('unavailable'), { response: { status: 503 } })
    apiClient.put.mockRejectedValue(failure)
    await expect(
      brandingService.update({ primaryColor: '#d4088c', secondaryColor: '#4a2a7d' }),
    ).rejects.toBe(failure)
    expect(logger.error).toHaveBeenCalledWith(
      failure,
      expect.objectContaining({ action: 'branding.update' }),
    )
  })
})

describe('profileService', () => {
  it('loads the signed-in user roles and permissions', async () => {
    apiClient.get.mockResolvedValue({
      data: {
        data: {
          roles: [{ key: 'admin', label: { ar: 'مدير النظام', en: 'System Administrator' } }],
          permissions: [
            {
              key: 'settings.branding.manage',
              module: 'settings',
              label: { ar: 'الهوية', en: 'Identity' },
            },
          ],
        },
        meta: {},
        errors: null,
      },
    })
    const { data } = await profileService.getMyPermissions()
    expect(apiClient.get).toHaveBeenCalledWith('/me/profile/permissions')
    expect(data.roles[0].key).toBe('admin')
    expect(data.permissions[0]).toEqual({
      key: 'settings.branding.manage',
      module: 'settings',
      label: { ar: 'الهوية', en: 'Identity' },
    })
  })

  it('tolerates an empty payload', async () => {
    apiClient.get.mockResolvedValue({ data: { data: null, meta: {}, errors: null } })
    const { data } = await profileService.getMyPermissions()
    expect(data).toEqual({ roles: [], permissions: [] })
  })
})
