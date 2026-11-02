import { notifySessionExpired } from '../sessionEvents'

const SESSION_LOSS_STATUSES = new Set([401, 419])

function isSessionLoss(error) {
  return SESSION_LOSS_STATUSES.has(error.response?.status) && !error.config?.skipSessionExpiry
}

export function reportSessionLoss(error) {
  if (isSessionLoss(error) && !error.sessionLossReported) {
    error.sessionLossReported = true
    notifySessionExpired()
  }
  throw error
}
