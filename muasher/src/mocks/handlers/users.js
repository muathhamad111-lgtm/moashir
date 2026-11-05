import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { paginate } from '../contract/pagination'
import {
  assessmentReject,
  assessmentRoute,
  readJson,
} from '../stores/assessments/assessmentRejection'
import { signedInActor } from '../stores/assessments/assessmentAccess'
import { sessionCan } from '../stores/sessionPermissions'
import {
  activateStaffUser,
  findStaffUser,
  inviteStaffUser,
  listStaffUsers,
  resendStaffInvitation,
  resetStaffMfa,
  setAssessorQualifications,
  setRequestScope,
  suspendStaffUser,
  unlockStaffUser,
  updateStaffUser,
  userResource,
} from '../stores/staffUsers'

const USERS = '/api/v1/users'
const USER = `${USERS}/:user`
const NUMERIC_ID = /^\d+$/
const PERMISSIONS = { VIEW: 'users.view', MANAGE: 'users.manage' }

function actorWith(permission) {
  const user = signedInActor()
  if (!user.superAdmin && !sessionCan(permission)) throw assessmentReject.forbidden()
  return user
}

function boundUser(params) {
  const user = NUMERIC_ID.test(params.user) ? findStaffUser(params.user) : null
  if (!user) throw assessmentReject.notFound()
  return user
}

function actionRoute(act) {
  return assessmentRoute(async ({ params, request }) => {
    const actor = actorWith(PERMISSIONS.MANAGE)
    const user = boundUser(params)
    return envelope(userResource(act(actor, user, await readJson(request))))
  })
}

export const usersHandlers = [
  http.get(
    USERS,
    assessmentRoute(({ request }) => {
      actorWith(PERMISSIONS.VIEW)
      const query = new URL(request.url).searchParams
      const { rows, stats } = listStaffUsers(query)
      const { items, pagination } = paginate(rows, query)
      return envelope(items.map(userResource), { pagination, stats })
    }),
  ),

  http.post(
    USERS,
    assessmentRoute(async ({ request }) => {
      actorWith(PERMISSIONS.MANAGE)
      return envelope(userResource(inviteStaffUser(await readJson(request))), {}, { status: 201 })
    }),
  ),

  http.get(
    USER,
    assessmentRoute(({ params }) => {
      actorWith(PERMISSIONS.VIEW)
      return envelope(userResource(boundUser(params)))
    }),
  ),

  http.put(
    USER,
    actionRoute((actor, user, body) => updateStaffUser(user, body)),
  ),
  http.post(`${USER}/suspend`, actionRoute(suspendStaffUser)),
  http.post(
    `${USER}/activate`,
    actionRoute((actor, user, body) => activateStaffUser(user, body)),
  ),
  http.post(
    `${USER}/unlock`,
    actionRoute((actor, user, body) => unlockStaffUser(user, body)),
  ),
  http.post(`${USER}/mfa/reset`, actionRoute(resetStaffMfa)),
  http.put(`${USER}/assessor-qualifications`, actionRoute(setAssessorQualifications)),
  http.put(`${USER}/request-scope`, actionRoute(setRequestScope)),

  http.post(
    `${USER}/invitation/resend`,
    assessmentRoute(({ params }) => {
      actorWith(PERMISSIONS.MANAGE)
      resendStaffInvitation(boundUser(params))
      return envelope(null, {}, { status: 202 })
    }),
  ),
]
