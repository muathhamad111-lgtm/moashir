const ERROR_PREFIX = 'finance.errors'

export function errorCodeOf(error) {
  const code = error?.normalized?.code
  return typeof code === 'string' ? code : null
}

export function errorStatusOf(error) {
  return error?.normalized?.status ?? error?.response?.status ?? null
}

export function financeErrorMessage(i18n, error, fallbackKey) {
  const code = errorCodeOf(error)
  const key = code ? `${ERROR_PREFIX}.${code}` : null
  if (key && i18n.te(key)) return i18n.t(key, { total: error?.normalized?.meta?.paymentTotal ?? '' })
  return error?.normalized?.message || i18n.t(fallbackKey)
}

export function firstFieldErrors(error) {
  const fields = error?.normalized?.fieldErrors ?? {}
  return Object.fromEntries(
    Object.entries(fields).map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] : messages]),
  )
}
