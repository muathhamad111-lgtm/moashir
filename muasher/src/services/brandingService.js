import { apiClient } from '@/api/client'
import { LOGO_VARIANT_BY_SURFACE } from '@/constants/branding'
import { payloadShape } from '@/utils/payloadShape'
import { serviceRequest } from './serviceRequest'

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
    dark: data.logoLight?.url ?? null,
    light: data.logoDark?.url ?? null,
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

async function themeRequest(action, execute, context) {
  const envelope = await serviceRequest(action, execute, { entity: 'settings-branding', ...context })
  return { ...envelope, data: toTheme(envelope.data) }
}

export const brandingService = {
  get() {
    return themeRequest('branding.get', () => apiClient.get('/settings/branding'))
  },
  update({ accentToken }) {
    const payload = { accentToken: accentToken }
    return themeRequest('branding.update', () => apiClient.put('/settings/branding', payload), {
      payloadShape: payloadShape(payload),
    })
  },
  reset() {
    return themeRequest('branding.reset', () => apiClient.post('/settings/branding/reset'))
  },
  async uploadLogo(file, surface = 'dark') {
    const variant = variantOf(surface)
    const form = new FormData()
    form.append('file', file)
    form.append('variant', variant)
    const envelope = await serviceRequest(
      'branding.uploadLogo',
      () => apiClient.post('/settings/branding/logo', form),
      { entity: 'settings-branding', variant },
    )
    return { ...envelope, data: { fileId: envelope.data?.fileId ?? null, url: envelope.data?.url ?? null } }
  },
}
