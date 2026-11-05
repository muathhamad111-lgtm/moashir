import { apiClient } from '@/api/client'
import { LOGO_VARIANT_BY_SURFACE } from '@/constants/branding'
import { contentImageUrl } from '@/utils/contentImage'
import { payloadShape } from '@/utils/payloadShape'
import { serviceRequest } from './serviceRequest'
import { DOMAIN_REJECTIONS } from './domainRequest'

function toPaletteEntry(entry) {
  return {
    token: entry.token,
    hex: entry.hex,
    textOnLightHex: entry.textOnLightHex,
    label: entry.label ?? null,
  }
}

function toLogos(data) {
  return {
    dark: contentImageUrl(data.logoLight) || null,
    light: contentImageUrl(data.logoDark) || null,
  }
}

function toTheme(data) {
  if (!data) return null
  return {
    accentToken: data.accentToken,
    logos: toLogos(data),
    palette: (data.palette ?? []).map(toPaletteEntry),
    updatedAt: data.updatedAt ?? null,
  }
}

function variantOf(surface) {
  return LOGO_VARIANT_BY_SURFACE[surface] ?? LOGO_VARIANT_BY_SURFACE.dark
}

function withReason(payload, reason) {
  return reason ? { ...payload, reason } : payload
}

async function themeRequest(action, execute, context = {}) {
  const envelope = await serviceRequest(
    action,
    execute,
    { entity: 'settings-branding', ...context },
    { expectedStatuses: DOMAIN_REJECTIONS },
  )
  return { ...envelope, data: toTheme(envelope.data) }
}

export const brandingService = {
  get() {
    return themeRequest('branding.get', () => apiClient.get('/settings/branding'))
  },
  update({ accentToken, reason = null }) {
    const payload = withReason({ accentToken }, reason)
    return themeRequest('branding.update', () => apiClient.put('/settings/branding', payload), {
      payloadShape: payloadShape(payload),
    })
  },
  reset({ reason = null } = {}) {
    const payload = withReason({}, reason)
    return themeRequest(
      'branding.reset',
      () => apiClient.post('/settings/branding/reset', payload),
      {
        payloadShape: payloadShape(payload),
      },
    )
  },
  async uploadLogo(file, surface = 'dark', reason = null) {
    const variant = variantOf(surface)
    const form = new FormData()
    form.append('file', file)
    form.append('variant', variant)
    if (reason) form.append('reason', reason)
    const envelope = await serviceRequest(
      'branding.uploadLogo',
      () => apiClient.post('/settings/branding/logo', form),
      { entity: 'settings-branding', variant },
      { expectedStatuses: DOMAIN_REJECTIONS },
    )
    return {
      ...envelope,
      data: {
        fileId: envelope.data?.fileId ?? null,
        url: contentImageUrl(envelope.data?.url) || null,
      },
    }
  },
}
