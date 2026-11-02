import { http } from '../contract/http'
import { envelope, noContent } from '../contract/responses'
import { actorWith, entityReject, entityRoute, readJson, signedInUser } from '../stores/entityRejection'
import { ENTITY_PERMISSIONS as P, accessOf, boundEntity } from '../stores/entityAccess'
import { deletionImpactResource, entityResourceFor } from '../stores/entitySerializers'
import { ERASURE_KEPT_RECORDS } from '@/constants/entityEnums'
import { PERMANENT_REASON_MIN, assertReason } from '../stores/entityValidation'
import {
  deactivate,
  deletionImpactOf,
  permanentlyDelete,
  reactivate,
  resendVerification,
  restore,
  softDelete,
} from '../stores/entityLifecycle'
import { sessionIsSuperAdmin } from '../stores/sessionPermissions'
import { deletePrivacyRequestsOf } from '../stores/privacyRequestsStore'
import { isDeleted } from '../stores/entitiesStore'
import { personalDataPackage } from '../stores/entityPersonalData'

const ENTITY_PATH = '/api/v1/entities/:id'

function resourceOf(user, entity) {
  return envelope(entityResourceFor(accessOf(user))(entity))
}

function reasonAction(permission, transition) {
  return entityRoute(async ({ params, request }) => {
    const user = actorWith(permission)
    const entity = boundEntity(params.id)
    const reason = assertReason(await readJson(request))
    return resourceOf(user, transition(entity, reason))
  })
}

function superAdmin() {
  const user = signedInUser()
  if (!sessionIsSuperAdmin()) throw entityReject.forbidden()
  return user
}

export const entityLifecycleHandlers = [
  http.post(`${ENTITY_PATH}/deactivate`, reasonAction(P.DEACTIVATE, deactivate)),

  http.post(`${ENTITY_PATH}/reactivate`, reasonAction(P.REACTIVATE, reactivate)),

  http.post(`${ENTITY_PATH}/restore`, reasonAction(P.DELETE, restore)),

  http.post(
    `${ENTITY_PATH}/verification/resend`,
    entityRoute(({ params }) => {
      actorWith(P.VERIFICATION_RESEND)
      const entity = resendVerification(boundEntity(params.id))
      return envelope({ verificationRemindedAt: entity.verificationRemindedAt }, {}, { status: 202 })
    }),
  ),

  http.get(
    `${ENTITY_PATH}/deletion-impact`,
    entityRoute(({ params }) => {
      actorWith(P.DELETE)
      const entity = boundEntity(params.id)
      const openRequests = deletionImpactOf(entity)
      return envelope(deletionImpactResource(openRequests, ERASURE_KEPT_RECORDS, { frozen: isDeleted(entity) }))
    }),
  ),

  http.delete(
    ENTITY_PATH,
    entityRoute(async ({ params, request }) => {
      actorWith(P.DELETE)
      const entity = boundEntity(params.id)
      const body = await readJson(request)
      const reason = assertReason(body)
      softDelete(entity, { ...body, reason })
      return noContent()
    }),
  ),

  http.delete(
    `${ENTITY_PATH}/permanent`,
    entityRoute(async ({ params, request }) => {
      superAdmin()
      const entity = boundEntity(params.id)
      const body = await readJson(request)
      assertReason(body, PERMANENT_REASON_MIN)
      const outcome = permanentlyDelete(entity, body)
      deletePrivacyRequestsOf(entity.id)
      return envelope(outcome)
    }),
  ),

  http.post(
    `${ENTITY_PATH}/export`,
    entityRoute(async ({ params, request }) => {
      actorWith(P.PRIVACY_MANAGE)
      const entity = boundEntity(params.id)
      assertReason(await readJson(request))
      return envelope(personalDataPackage(entity))
    }),
  ),
]
