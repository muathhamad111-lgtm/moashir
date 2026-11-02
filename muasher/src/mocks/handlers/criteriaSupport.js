import { delay } from 'msw'
import { paginate } from '../contract/pagination'
import { currentSessionUser } from '../stores/sessionStore'
import { sessionCan } from '../stores/sessionPermissions'
import { CriteriaRejection, reject, rejectionResponse } from '../stores/criteriaRejection'

export const CRITERIA_LATENCY_MS = 180

export const CRITERIA_PERMISSIONS = {
  VIEW: 'criteria.view',
  MANAGE: 'criteria.manage',
  PUBLISH: 'criteria.publish',
  VIEW_PUBLISHED: 'assessment.criteria.view',
}

export function actorWith(permission) {
  const user = currentSessionUser()
  if (!user) throw reject.unauthenticated()
  if (!sessionCan(permission)) throw reject.forbidden()
  return user
}

export async function readBody(request) {
  const text = await request.text()
  return text ? JSON.parse(text) : {}
}

export function found(node) {
  if (!node) throw reject.notFound()
  return node
}

export function criteriaRoute(resolve) {
  return async (info) => {
    await delay(CRITERIA_LATENCY_MS)
    try {
      return await resolve(info)
    } catch (error) {
      if (error instanceof CriteriaRejection) return rejectionResponse(error, info.request)
      throw error
    }
  }
}

export { paginate }
