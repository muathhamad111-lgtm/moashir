import { payloadShape } from '@/utils/payloadShape'
import { serviceRequest } from './serviceRequest'

export const DOMAIN_REJECTIONS = [409, 422, 429]

export function domainRequests(prefix, entity) {
  return {
    read(action, execute, params, expectedStatuses = []) {
      const context = params ? { entity, paramsShape: payloadShape(params) } : { entity }
      return serviceRequest(`${prefix}.${action}`, execute, context, { expectedStatuses })
    },
    write(action, execute, payload, expectedStatuses = []) {
      return serviceRequest(
        `${prefix}.${action}`,
        execute,
        { entity, payloadShape: payloadShape(payload ?? {}) },
        { expectedStatuses: [...DOMAIN_REJECTIONS, ...expectedStatuses] },
      )
    },
  }
}
