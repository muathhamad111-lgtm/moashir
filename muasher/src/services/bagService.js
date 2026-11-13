import { payloadShape } from '@/utils/payloadShape'
import { apiClient } from '@/api/client'
import { serviceRequest } from './serviceRequest'

export const bagService = {
  getProduct() {
    return serviceRequest('bag.getProduct', () => apiClient.get('/bag/product'), { entity: 'bag' })
  },

  getHistory() {
    return serviceRequest('bag.getHistory', () => apiClient.get('/bag/history'), { entity: 'bag' })
  },

  getEligibility() {
    return serviceRequest('bag.getEligibility', () => apiClient.get('/bag/eligibility'), {
      entity: 'bag',
    })
  },

  getSubscription() {
    return serviceRequest('bag.getSubscription', () => apiClient.get('/bag/subscription'), {
      entity: 'bag',
    })
  },

  getEntityView() {
    return serviceRequest('bag.getEntityView', () => apiClient.get('/bag/entity-view'), {
      entity: 'bag',
    })
  },

  getConsultantView() {
    return serviceRequest('bag.getConsultantView', () => apiClient.get('/bag/consultant-view'), {
      entity: 'bag',
    })
  },

  openStageGaps(gapIds) {
    const payload = { gapIds }
    return serviceRequest(
      'bag.openStageGaps',
      () => apiClient.post('/bag/open-stage-gaps', payload),
      { entity: 'bag', payloadShape: payloadShape(payload) },
    )
  },

  attachHelperFile(payload = {}) {
    return serviceRequest(
      'bag.attachHelperFile',
      () => apiClient.post('/bag/helper-files', payload),
      { entity: 'bag', payloadShape: payloadShape(payload) },
    )
  },

  listStages() {
    return serviceRequest('bag.listStages', () => apiClient.get('/bag/stages'), { entity: 'bag' })
  },

  updateGapGuidance(payload = {}) {
    return serviceRequest(
      'bag.updateGapGuidance',
      () => apiClient.post('/bag/gap-guidance', payload),
      { entity: 'bag', payloadShape: payloadShape(payload) },
    )
  },

  getReassessment() {
    return serviceRequest('bag.getReassessment', () => apiClient.get('/bag/reassessment'), {
      entity: 'bag',
    })
  },
}
