const SUPPORTED_LOCALES = ['ar', 'en']
const DEFAULT_LOCALE = 'ar'

export function requestLocale(request) {
  const header = request?.headers?.get('accept-language') ?? ''
  const preferred = header.split(',').map((part) => part.trim().slice(0, 2).toLowerCase())
  return preferred.find((candidate) => SUPPORTED_LOCALES.includes(candidate)) ?? DEFAULT_LOCALE
}

export function interpolate(template, params = {}) {
  return Object.entries(params).reduce(
    (text, [key, value]) => text.replaceAll(`:${key}`, String(value)),
    template,
  )
}
