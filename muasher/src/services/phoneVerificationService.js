import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const SMS_UNAVAILABLE = 503
const { write } = domainRequests('phoneVerification', 'phone-verification')

export const phoneVerificationService = {
  start() {
    return write('start', () => apiClient.post('/me/phone/verification', {}), {}, [SMS_UNAVAILABLE])
  },

  confirm({ challengeId, code }) {
    const payload = { challengeId, code }
    return write(
      'confirm',
      () => apiClient.post('/me/phone/verification/confirm', payload),
      payload,
    )
  },
}
