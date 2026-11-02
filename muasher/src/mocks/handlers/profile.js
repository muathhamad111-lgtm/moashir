import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope, errorResponse } from '../contract/responses'
import { findPermission, findRole } from '../stores/permissionsStore'
import { currentSessionUser, updateMockSession } from '../stores/sessionStore'
import { sessionPermissions } from '../stores/sessionPermissions'
import { rememberLocale } from '../stores/preferencesStore'
import { DEFAULT_LOCALE, SUPPORTED_LOCALES } from '@/constants/locales'

const THEME_VALUES = ['light', 'dark', 'system']
const DEFAULT_THEME_PREFERENCE = 'system'

function preferencesOf(user) {
  return { locale: user.locale ?? DEFAULT_LOCALE, theme: user.theme ?? DEFAULT_THEME_PREFERENCE }
}

function invalidPreferences(body) {
  const fields = {}
  if (body.locale !== undefined && !SUPPORTED_LOCALES.includes(body.locale))
    fields.locale = ['The selected locale is invalid.']
  if (body.theme !== undefined && !THEME_VALUES.includes(body.theme))
    fields.theme = ['The selected theme is invalid.']
  return Object.keys(fields).length
    ? errorResponse(422, 'validation_failed', 'The given data was invalid.', fields)
    : null
}

export const profileHandlers = [
  http.get('/api/v1/me/profile/permissions', async () => {
    await delay(220)
    const user = currentSessionUser()
    if (!user) return errorResponse(401, 'unauthenticated', 'Unauthenticated.')
    return envelope({
      roles: user.roles.map((key) => ({
        key,
        label: findRole(key)?.label ?? { ar: key, en: key },
      })),
      permissions: sessionPermissions().map((key) => {
        const permission = findPermission(key)
        return { key, module: permission.module, label: permission.label }
      }),
    })
  }),

  http.get('/api/v1/me/preferences', async () => {
    await delay(120)
    const user = currentSessionUser()
    if (!user) return errorResponse(401, 'unauthenticated', 'Unauthenticated.')
    return envelope(preferencesOf(user))
  }),

  http.patch('/api/v1/me/preferences', async ({ request }) => {
    await delay(160)
    const user = currentSessionUser()
    if (!user) return errorResponse(401, 'unauthenticated', 'Unauthenticated.')
    const body = await request.json()
    const invalid = invalidPreferences(body)
    if (invalid) return invalid
    const updated = updateMockSession(body.locale ? { locale: body.locale } : {})
    if (body.locale) rememberLocale(user.id, body.locale)
    return envelope(preferencesOf({ ...updated, theme: body.theme ?? null }))
  }),
]
