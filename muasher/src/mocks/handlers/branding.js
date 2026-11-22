import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { DEFAULT_BRAND_COLORS, DERIVED_BRAND_COLORS, LOGO_RULES } from '@/constants/branding'
import { brandContrastPairs, deriveBrandScheme, normalizeHex } from '@/utils/brandingContrast'
import {
  getBrandingTheme,
  isLogoVariant,
  resetBrandingTheme,
  storeBrandingLogo,
  updateBrandingColors,
} from '../stores/brandingStore'
import { contentReject, contentRoute, readJson } from '../stores/content/contentRejection'
import {
  CONTENT_PERMISSIONS,
  requireContentPermission,
  signedInActor,
} from '../stores/content/contentAccess'
import { createFieldErrors } from '../stores/content/contentValidation'
import { validateImage } from '../stores/content/imageUploads'

const REASON_MIN = 10
const MINIMUM_CONTRAST = '4.5'

function themeResource() {
  const theme = getBrandingTheme()
  const scheme = deriveBrandScheme(
    { primary: theme.primaryColor, secondary: theme.secondaryColor },
    theme.colorOverrides,
  )
  return { ...theme, scheme, defaultColors: { ...DEFAULT_BRAND_COLORS } }
}

function requireReason(user, reason) {
  const text = typeof reason === 'string' ? reason.trim() : ''
  if (text) {
    const errors = createFieldErrors()
    errors.check(text.length >= REASON_MIN, 'reason', 'reasonLength')
    errors.throwIfAny()
  }
  if (user.superAdmin && !text) throw contentReject.reasonRequired()
}

function requireOverrides(raw, errors) {
  const entries = Object.entries(raw ?? {})
  errors.check(
    entries.every(([key]) => DERIVED_BRAND_COLORS.includes(key)),
    'colorOverrides',
    'unknownKey',
  )
  entries.forEach(([key, value]) =>
    errors.check(normalizeHex(value), `colorOverrides.${key}`, 'hexColor'),
  )
  return Object.fromEntries(entries.map(([key, value]) => [key, normalizeHex(value)]))
}

function requireColors(body) {
  const errors = createFieldErrors()
  const colors = {
    primary: normalizeHex(body.primaryColor),
    secondary: normalizeHex(body.secondaryColor),
    overrides: requireOverrides(body.colorOverrides, errors),
  }
  errors.check(colors.primary, 'primaryColor', 'hexColor')
  errors.check(colors.secondary, 'secondaryColor', 'hexColor')
  errors.throwIfAny()
  return colors
}

function failingPairs(colors) {
  return brandContrastPairs(deriveBrandScheme(colors, colors.overrides))
    .filter((pair) => !pair.passes)
    .map((pair) => ({
      pair: pair.key,
      foreground: pair.foreground,
      background: pair.background,
      ratio: pair.ratio.toFixed(2),
      minimum: MINIMUM_CONTRAST,
    }))
}

function applyColors(colors) {
  const failures = failingPairs(colors)
  if (failures.length) {
    throw contentReject.contrastFail(failures, `${failures[0].pair.split('.')[0]}Color`)
  }
  updateBrandingColors(colors)
}

export const brandingHandlers = [
  http.get(
    '/api/v1/settings/branding',
    contentRoute(async () => {
      await delay(40)
      signedInActor()
      return envelope(themeResource())
    }),
  ),

  http.put(
    '/api/v1/settings/branding',
    contentRoute(async ({ request }) => {
      const user = requireContentPermission(CONTENT_PERMISSIONS.BRANDING)
      const body = await readJson(request)
      const colors = requireColors(body)
      requireReason(user, body.reason)
      applyColors(colors)
      return envelope(themeResource())
    }),
  ),

  http.post(
    '/api/v1/settings/branding/logo',
    contentRoute(async ({ request }) => {
      const user = requireContentPermission(CONTENT_PERMISSIONS.BRANDING)
      const form = await request.formData()
      requireReason(user, form.get('reason'))
      const errors = createFieldErrors()
      errors.check(isLogoVariant(form.get('variant')), 'variant', 'required')
      errors.throwIfAny()
      const file = await validateImage(form.get('file'), LOGO_RULES, 'logoType')
      return envelope(storeBrandingLogo(form.get('variant'), file))
    }),
  ),

  http.post(
    '/api/v1/settings/branding/reset',
    contentRoute(async ({ request }) => {
      const user = requireContentPermission(CONTENT_PERMISSIONS.BRANDING)
      requireReason(user, (await readJson(request)).reason)
      resetBrandingTheme()
      return envelope(themeResource())
    }),
  ),
]
