import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { LOGO_RULES } from '@/constants/branding'
import { accentContrastPairs } from '@/utils/brandingContrast'
import { brandingPaletteFixture } from '../fixtures/branding'
import {
  getBrandingTheme,
  isLogoVariant,
  paletteEntry,
  resetBrandingTheme,
  storeBrandingLogo,
  updateBrandingAccent,
} from '../stores/brandingStore'
import { sessionCan } from '../stores/sessionPermissions'
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

function themeResource(user) {
  const canManage = user.superAdmin || sessionCan(CONTENT_PERMISSIONS.BRANDING)
  return { ...getBrandingTheme(), palette: canManage ? brandingPaletteFixture : [] }
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

function failingPairs(entry) {
  return accentContrastPairs(entry)
    .filter((pair) => !pair.passes)
    .map((pair) => ({
      pair: pair.key,
      foreground: pair.foreground,
      background: pair.background,
      ratio: pair.ratio.toFixed(2),
      minimum: MINIMUM_CONTRAST,
    }))
}

function applyAccent(token) {
  const entry = paletteEntry(token)
  if (!entry) throw contentReject.tokenUnknown()
  const failures = failingPairs(entry)
  if (failures.length) throw contentReject.contrastFail(failures)
  updateBrandingAccent(token)
}

export const brandingHandlers = [
  http.get(
    '/api/v1/settings/branding',
    contentRoute(async () => {
      await delay(40)
      return envelope(themeResource(signedInActor()))
    }),
  ),

  http.put(
    '/api/v1/settings/branding',
    contentRoute(async ({ request }) => {
      const user = requireContentPermission(CONTENT_PERMISSIONS.BRANDING)
      const body = await readJson(request)
      requireReason(user, body.reason)
      applyAccent(body.accentToken)
      return envelope(themeResource(user))
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
      return envelope(themeResource(user))
    }),
  ),
]
