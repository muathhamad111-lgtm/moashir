import { ISSUANCE_MODES } from '@/constants/certificates'

const ROOT = 'review.confirmComplete'

// The completion question never promises an automatic certificate the platform will not issue.
export function completeMessageKey({ hasPendingChanges, offersCertificate, issuanceMode }) {
  if (hasPendingChanges) return `${ROOT}.pendingMessage`
  if (!offersCertificate) return `${ROOT}.message`
  if (issuanceMode === ISSUANCE_MODES.AUTOMATIC) return `${ROOT}.certificateMessage`
  if (issuanceMode === ISSUANCE_MODES.MANUAL) return `${ROOT}.manualCertificateMessage`
  return `${ROOT}.message`
}
