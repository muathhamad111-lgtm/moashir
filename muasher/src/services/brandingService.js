import { apiClient } from '@/api/client'
import {
  FAVICON_SURFACE,
  LOGO_VARIANT_BY_SURFACE,
  SIDEBAR_LOGO_SURFACE,
  SITE_LOGO_SURFACE,
} from '@/constants/branding'
import { contentImageUrl } from '@/utils/contentImage'
import { payloadShape } from '@/utils/payloadShape'
import { serviceRequest } from './serviceRequest'
import { DOMAIN_REJECTIONS } from './domainRequest'

function toScheme(scheme) {
  if (!scheme) return null
  return {
    primary: scheme.primary,
    onPrimary: scheme.onPrimary,
    primaryText: scheme.primaryText,
    secondary: scheme.secondary,
    onSecondary: scheme.onSecondary,
  }
}

function toColors(colors) {
  return colors ? { primary: colors.primary, secondary: colors.secondary } : null
}

function toLogos(data) {
  return {
    [SIDEBAR_LOGO_SURFACE]: contentImageUrl(data.logoLight) || null,
    [FAVICON_SURFACE]: contentImageUrl(data.favicon) || null,
    [SITE_LOGO_SURFACE]: contentImageUrl(data.logoSite) || null,
  }
}

function toTheme(data) {
  if (!data) return null
  return {
    colors: { primary: data.primaryColor, secondary: data.secondaryColor },
    scheme: toScheme(data.scheme),
    defaultColors: toColors(data.defaultColors),
    logos: toLogos(data),
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
  update({ primaryColor, secondaryColor, reason = null }) {
    const payload = withReason({ primaryColor, secondaryColor }, reason)
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
