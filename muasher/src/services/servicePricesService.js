import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toServicePrices } from './mappers/accredited'

const { read } = domainRequests('servicePrices', 'service-prices')

export const servicePricesService = {
  getServicePrices() {
    return read('getServicePrices', () => apiClient.get('/content/prices')).then((envelope) => ({
      ...envelope,
      data: toServicePrices(envelope.data),
    }))
  },
}
