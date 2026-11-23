import { HttpResponse } from 'msw'
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
  sendStaffPasswordResetLink,
  revokeStaffSessions,
  cancelStaffInvitation,
  staffUsersCsv,
  resetStaffMfa,
  setAssessorQualifications,
  setRequestScope,
  suspendStaffUser,
  unlockStaffUser,
  updateStaffUser,
  userResource,
} from '../stores/staffUsers'
import { currentNda } from '../stores/ndaCurrent'
import { ndaScopeRoles, ndaStatusOf } from '../stores/ndaStore'

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
    `${USERS}/export`,
    assessmentRoute(({ request }) => {
      actorWith(PERMISSIONS.VIEW)
      actorWith('users.export')
      const { csv, count } = staffUsersCsv(new URL(request.url).searchParams)
      return new HttpResponse(csv, {
        headers: {
          'Content-Type': 'text/csv; charset=UTF-8',
          'Content-Disposition': 'attachment; filename=users.csv',
          'X-Export-Row-Count': String(count),
        },
      })
    }),
  ),
  http.get(
    USERS,
    assessmentRoute(({ request }) => {
      actorWith(PERMISSIONS.VIEW)
      const query = new URL(request.url).searchParams
      const { rows, stats } = listStaffUsers(query)
      const current = currentNda()
      const visible =
        query.get('filter[nda]') === 'pending'
          ? rows.filter((user) => ndaStatusOf(user, current) === 'pending')
          : rows
      const { items, pagination } = paginate(visible, query)
      return envelope(
        items.map((user) => ({ ...userResource(user), ndaStatus: ndaStatusOf(user, current) })),
        {
          pagination,
          stats,
          ndaScopeRoles: ndaScopeRoles(),
          ndaCurrentVersion: current?.version ?? null,
        },
      )
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

  http.post(`${USER}/sessions/revoke`, actionRoute(revokeStaffSessions)),
  http.delete(
    `${USER}/invitation`,
    assessmentRoute(({ params }) => {
      const actor = actorWith(PERMISSIONS.MANAGE)
      cancelStaffInvitation(actor, boundUser(params))
      return new HttpResponse(null, { status: 204 })
    }),
  ),
  http.post(
    `${USER}/password/reset-link`,
    assessmentRoute(({ params }) => {
      const actor = actorWith(PERMISSIONS.MANAGE)
      sendStaffPasswordResetLink(actor, boundUser(params))
      return envelope(null, {}, { status: 202 })
    }),
  ),
  http.post(
    `${USER}/invitation/resend`,
    assessmentRoute(({ params }) => {
      actorWith(PERMISSIONS.MANAGE)
      resendStaffInvitation(boundUser(params))
      return envelope(null, {}, { status: 202 })
    }),
  ),
]
