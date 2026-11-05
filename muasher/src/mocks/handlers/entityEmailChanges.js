import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { requestLocale } from '../contract/locale'
import { paginate } from '../contract/pagination'
import { EMAIL_CHANGE_STATUSES } from '@/constants/entityEnums'
import { entityNotices } from '../fixtures/entityMessages'
import {
  actorWith,
  entityReject,
  entityRoute,
  readJson,
  signedInUser,
} from '../stores/entityRejection'
import { ENTITY_PERMISSIONS as P, ownEntityOf } from '../stores/entityAccess'
import { emailChangeResource, ownEmailChangeResource } from '../stores/entitySerializers'
import { assertEmail, assertReason, rejectField } from '../stores/entityValidation'
import { ownerEmailTaken } from '../stores/entitiesStore'
import {
  OTP_PURPOSES,
  acceptsCode,
  challengePayload,
  consumeChallenge,
  createChallenge,
  findChallenge,
} from '../stores/otpChallenges'
import { sessionCan } from '../stores/sessionPermissions'
import {
  approveChange,
  confirmChange,
  findEmailChange,
  listEmailChanges,
  rejectChange,
  requestChange,
} from '../stores/entityEmailChanges'

const ADMIN_PATH = '/api/v1/admin/entity-email-changes'

function ownEntity() {
  const user = signedInUser()
  if (!user.entityId || !sessionCan(P.PROFILE_EDIT)) throw entityReject.forbidden()
  return ownEntityOf(user)
}

function assertNewEmail(entity, value) {
  const email = assertEmail('newEmail', value).toLowerCase()
  if (email === entity.owner.email.toLowerCase()) rejectField('newEmail', 'field.new_email_same')
  if (ownerEmailTaken(email)) rejectField('newEmail', 'field.email_taken')
  return email
}

function boundChange(id) {
  const change = findEmailChange(id)
  if (!change) throw entityReject.notFound()
  return change
}

export const entityEmailChangesHandlers = [
  http.post(
    '/api/v1/entities/me/email/change',
    entityRoute(async ({ request }) => {
      const entity = ownEntity()
      const email = assertNewEmail(entity, (await readJson(request)).newEmail)
      const challenge = createChallenge(
        { kind: 'entity', record: entity },
        'email',
        OTP_PURPOSES.EMAIL_CHANGE,
      )
      requestChange(entity, email, challenge.id)
      const message = entityNotices.emailChangeOtpSent[requestLocale(request)]
      return envelope({ otp: challengePayload(challenge), message })
    }),
  ),

  http.post(
    '/api/v1/entities/me/email/confirm',
    entityRoute(async ({ request }) => {
      const entity = ownEntity()
      const body = await readJson(request)
      const challenge = findChallenge(body.challengeId, OTP_PURPOSES.EMAIL_CHANGE)
      if (!acceptsCode(challenge, body.code)) throw entityReject.otpInvalid()
      consumeChallenge(challenge.id)
      return envelope(ownEmailChangeResource(confirmChange(entity, challenge.id)))
    }),
  ),

  http.get(
    ADMIN_PATH,
    entityRoute(({ request }) => {
      actorWith(P.EMAIL_CHANGE_APPROVE)
      const query = new URL(request.url).searchParams
      const status = query.get('filter[status]') || EMAIL_CHANGE_STATUSES.AWAITING_APPROVAL
      const { items, pagination } = paginate(listEmailChanges(status), query)
      return envelope(items.map(emailChangeResource), { pagination })
    }),
  ),

  http.post(
    `${ADMIN_PATH}/:id/approve`,
    entityRoute(({ params }) => {
      actorWith(P.EMAIL_CHANGE_APPROVE)
      return envelope(emailChangeResource(approveChange(boundChange(params.id))))
    }),
  ),

  http.post(
    `${ADMIN_PATH}/:id/reject`,
    entityRoute(async ({ params, request }) => {
      actorWith(P.EMAIL_CHANGE_APPROVE)
      const change = boundChange(params.id)
      const reason = assertReason(await readJson(request))
      return envelope(emailChangeResource(rejectChange(change, reason)))
    }),
  ),
]
