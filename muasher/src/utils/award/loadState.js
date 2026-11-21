const NOT_FOUND = 404
const FORBIDDEN = 403

export function failureState(error) {
  const status = error?.normalized?.status
  if (status === NOT_FOUND) return 'missing'
  if (status === FORBIDDEN) return 'blocked'
  return 'error'
}

export function errorMessageOf(error) {
  return error?.normalized?.message ?? ''
}
