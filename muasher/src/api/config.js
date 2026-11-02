const configuredBaseUrl = import.meta.env.VITE_API_BASE_URL

export const API_BASE_URL = configuredBaseUrl || '/api/v1'

export const CSRF_COOKIE_PATH = '/sanctum/csrf-cookie'

export const API_ORIGIN = originOf(API_BASE_URL)

function originOf(url) {
  try {
    return new URL(url).origin
  } catch {
    return ''
  }
}
