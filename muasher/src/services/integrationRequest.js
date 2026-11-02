import { payloadShape } from '@/utils/payloadShape'
import { serviceRequest } from './serviceRequest'
import { DOMAIN_REJECTIONS } from './domainRequest'

export const PROVIDER_REJECTION = 502
export const MISSING_RESOURCE = 404

const EXPECTED_ANSWERS = [...DOMAIN_REJECTIONS, MISSING_RESOURCE, PROVIDER_REJECTION]

export function integrationRequests(prefix, entity) {
  return {
    read(action, execute, params) {
      const context = params ? { entity, paramsShape: payloadShape(params) } : { entity }
      return serviceRequest(`${prefix}.${action}`, execute, context)
    },
    write(action, execute, payload) {
      return serviceRequest(
        `${prefix}.${action}`,
        execute,
        { entity, payloadShape: payloadShape(payload ?? {}) },
        { expectedStatuses: EXPECTED_ANSWERS },
      )
    },
  }
}
