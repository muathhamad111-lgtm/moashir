import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const { write } = domainRequests('contactPrivacy', 'contact-privacy')

export const contactPrivacyService = {
  exportByEmail({ email, reason }) {
    const payload = { email, reason }
    return write(
      'export',
      () => apiClient.post('/privacy/contact-messages/export', payload),
      payload,
    )
  },

  eraseByEmail({ email, reason }) {
    const payload = { email, reason }
    return write('erase', () => apiClient.post('/privacy/contact-messages/erase', payload), payload)
  },
}
