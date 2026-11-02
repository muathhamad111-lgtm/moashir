export function normalizeApiError(error) {
  const body = error.response?.data
  return {
    message: body?.message ?? error.message,
    fieldErrors: body?.errors ?? {},
    meta: body?.meta ?? {},
    code: body?.code ?? error.response?.status ?? null,
    status: error.response?.status ?? null,
  }
}

export function attachNormalizedError(error) {
  error.normalized = normalizeApiError(error)
  throw error
}
