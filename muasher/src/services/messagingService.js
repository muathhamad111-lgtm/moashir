import { apiClient } from '@/api/client'
import { payloadShape } from '@/utils/payloadShape'
import { serviceRequest } from './serviceRequest'

export const messagingService = {
  sendSms(payload) {
    return serviceRequest('messaging.sendSms', () => apiClient.post('/messaging/sms/send', payload), {
      entity: 'sms',
      payloadShape: payloadShape(payload),
    })
  },

  listSegments() {
    return serviceRequest('messaging.listSegments', () => apiClient.get('/messaging/segments'), {
      entity: 'segment',
    })
  },
  searchRecipients(params = {}) {
    return serviceRequest(
      'messaging.searchRecipients',
      () => apiClient.get('/messaging/recipients', { params }),
      { entity: 'recipient', paramsShape: payloadShape(params) },
    )
  },
  sendEmailCampaign(payload) {
    return serviceRequest(
      'messaging.sendEmailCampaign',
      () => apiClient.post('/messaging/email/send', payload),
      { entity: 'email-campaign', payloadShape: payloadShape(payload) },
    )
  },

  listAbandonedCarts(params = {}) {
    return serviceRequest(
      'messaging.listAbandonedCarts',
      () => apiClient.get('/messaging/abandoned-carts', { params }),
      { entity: 'abandoned-cart', paramsShape: payloadShape(params) },
    )
  },
  sendCartCampaign(payload) {
    return serviceRequest(
      'messaging.sendCartCampaign',
      () => apiClient.post('/messaging/abandoned-carts/campaign', payload),
      { entity: 'abandoned-cart', payloadShape: payloadShape(payload) },
    )
  },
}
