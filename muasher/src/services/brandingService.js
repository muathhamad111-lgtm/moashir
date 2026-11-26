import { apiClient } from '@/api/client'
import {
  DERIVED_BRAND_COLORS,
  FAVICON_SURFACE,
  LOGO_VARIANT_BY_SURFACE,
  SIDEBAR_LOGO_SURFACE,
  SITE_LOGO_SURFACE,
} from '@/constants/branding'
import { BRAND_SCHEME_KEYS } from '@/utils/brandingContrast'
import { contentImageUrl } from '@/utils/contentImage'
import { payloadShape } from '@/utils/payloadShape'
import { serviceRequest } from './serviceRequest'
import { DOMAIN_REJECTIONS } from './domainRequest'

function toScheme(scheme) {
  if (!scheme) return null
  return Object.fromEntries(BRAND_SCHEME_KEYS.map((key) => [key, scheme[key]]))
}

function toOverrides(overrides) {
  return Object.fromEntries(
    DERIVED_BRAND_COLORS.filter((key) => overrides?.[key]).map((key) => [key, overrides[key]]),
  )
}

function toRail(data) {
  return { top: data.sidebarTopColor ?? null, bottom: data.sidebarBottomColor ?? null }
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
    overrides: toOverrides(data.colorOverrides),
    rail: toRail(data),
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
  update({
    primaryColor,
    secondaryColor,
    colorOverrides = {},
    sidebarTopColor = null,
    sidebarBottomColor = null,
    reason = null,
  }) {
    const payload = withReason(
      { primaryColor, secondaryColor, colorOverrides, sidebarTopColor, sidebarBottomColor },
      reason,
    )
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
