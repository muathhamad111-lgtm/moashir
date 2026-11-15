export function fileUploadMessage(failure, fallback) {
  const normalized = failure?.normalized ?? {}
  const field = normalized.fieldErrors?.file
  if (field) return Array.isArray(field) ? field[0] : field
  return normalized.message || fallback
}
