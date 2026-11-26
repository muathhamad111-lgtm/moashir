export function renameFieldErrors(error, aliases) {
  const fieldErrors = error?.normalized?.fieldErrors
  if (!fieldErrors) return error
  error.normalized.fieldErrors = Object.fromEntries(
    Object.entries(fieldErrors).map(([field, messages]) => [aliases[field] ?? field, messages]),
  )
  return error
}
