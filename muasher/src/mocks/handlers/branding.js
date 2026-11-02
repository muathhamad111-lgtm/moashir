import { HttpResponse, delay } from 'msw'
import { http } from '../contract/http'
import { LOGO_RULES } from '@/constants/branding'
import { brandingPaletteFixture } from '../fixtures/branding'
import {
  getBrandingTheme,
  isLogoVariant,
  isPaletteToken,
  resetBrandingTheme,
  storeBrandingLogo,
  updateBrandingAccent,
} from '../stores/brandingStore'
import { sessionCan } from '../stores/sessionPermissions'

function envelope(data, meta = {}) {
  return HttpResponse.json({ data, meta, errors: null })
}

function errorResponse(message, status, code, fieldErrors = {}) {
  return HttpResponse.json({ message, errors: fieldErrors, code }, { status })
}

function canManageBranding() {
  return sessionCan('settings.branding.manage')
}

function themeResource() {
  const theme = getBrandingTheme()
  return {
    ...theme,
    palette: canManageBranding() ? brandingPaletteFixture : [],
  }
}

function forbidden() {
  return errorResponse('This action is restricted to the System Administrator.', 403, 'forbidden')
}

async function fileToDataUrl(file) {
  const bytes = new Uint8Array(await file.arrayBuffer())
  let binary = ''
  bytes.forEach((byte) => {
    binary += String.fromCharCode(byte)
  })
  return `data:${file.type};base64,${btoa(binary)}`
}

export const brandingHandlers = [
  http.get('/api/v1/settings/branding', async () => {
    await delay(160)
    return envelope(themeResource())
  }),

  http.put('/api/v1/settings/branding', async ({ request }) => {
    await delay(320)
    if (!canManageBranding()) return forbidden()
    const body = await request.json()
    if (!isPaletteToken(body?.accentToken)) {
      return errorResponse('The selected color is not part of the approved palette.', 422, 'token.unknown', {
        accentToken: 'token.unknown',
      })
    }
    updateBrandingAccent(body.accentToken)
    return envelope(themeResource())
  }),

  http.post('/api/v1/settings/branding/logo', async ({ request }) => {
    await delay(420)
    if (!canManageBranding()) return forbidden()
    const form = await request.formData()
    const file = form.get('file')
    const variant = form.get('variant')
    if (!isLogoVariant(variant)) {
      return errorResponse('The logo variant is invalid.', 422, 'validation_failed', { variant: ['validation.in'] })
    }
    if (!(file instanceof File) || !LOGO_RULES.mimeTypes.includes(file.type)) {
      return errorResponse('Unsupported file type.', 415, 'file.type_not_allowed')
    }
    if (file.size > LOGO_RULES.maxBytes) {
      return errorResponse('The file is too large.', 413, 'file.too_large')
    }
    const logo = storeBrandingLogo(variant, await fileToDataUrl(file))
    return envelope(logo)
  }),

  http.post('/api/v1/settings/branding/reset', async () => {
    await delay(260)
    if (!canManageBranding()) return forbidden()
    resetBrandingTheme()
    return envelope(themeResource())
  }),
]
