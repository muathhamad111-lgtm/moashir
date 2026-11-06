import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope, errorResponse } from '../contract/responses'
import {
  findPermission,
  findRole,
  isRoleEditable,
  listCatalog,
  listRoles,
  lockedViolations,
  roleMatrix,
  setRolePermissions,
  unknownPermissionKeys,
  updatePermissionLabels,
} from '../stores/permissionsStore'
import { currentSessionUser } from '../stores/sessionStore'
import { sessionCan } from '../stores/sessionPermissions'
import { failsRandomly } from '../contract/faults'

const MATRIX_VIEW = 'roles.matrix.view'
const MATRIX_MANAGE = 'roles.matrix.manage'
const USERS_VIEW = 'users.view'

const maybeFail = failsRandomly(0.05)

function denied() {
  if (!currentSessionUser()) return errorResponse(401, 'unauthenticated', 'Unauthenticated.')
  return errorResponse(403, 'forbidden', 'This action is unauthorized.')
}

function labelErrors(body) {
  const errors = {}
  ;['ar', 'en'].forEach((locale) => {
    const value = String(body.label?.[locale] ?? '').trim()
    if (value.length < 2) errors[`label.${locale}`] = ['The label must be at least 2 characters.']
  })
  return errors
}

export const permissionsHandlers = [
  http.get('/api/v1/permissions', async ({ request }) => {
    await delay(260)
    if (!sessionCan(MATRIX_VIEW)) return denied()
    if (maybeFail()) return errorResponse(503, 'service_unavailable', 'Service unavailable')
    const module = new URL(request.url).searchParams.get('module')
    return envelope({ permissions: listCatalog(module), roles: listRoles(), matrix: roleMatrix() })
  }),

  http.put('/api/v1/permissions/:key', async ({ params, request }) => {
    await delay(320)
    if (!sessionCan(MATRIX_MANAGE)) return denied()
    if (!findPermission(params.key)) return errorResponse(404, 'not_found', 'Not found.')
    const body = await request.json()
    const errors = labelErrors(body)
    if (Object.keys(errors).length)
      return errorResponse(422, 'validation_failed', 'Validation failed.', errors)
    return envelope(updatePermissionLabels(params.key, body))
  }),

  http.put('/api/v1/roles/:role/permissions', async ({ params, request }) => {
    await delay(320)
    if (!sessionCan(MATRIX_MANAGE)) return denied()
    const role = findRole(params.role)
    if (!role) return errorResponse(404, 'not_found', 'Not found.')
    if (!isRoleEditable(role.key))
      return errorResponse(422, 'role.not_editable', 'This role cannot be edited.')
    const keys = (await request.json()).permissionKeys ?? []
    if (unknownPermissionKeys(keys).length) {
      return errorResponse(422, 'permission.unknown', 'The permission is unknown.', {
        permissionKeys: ['The permission is unknown.'],
      })
    }
    if (lockedViolations(role.key, keys).length) {
      return errorResponse(422, 'permission.locked', 'This permission is locked to its roles.', {
        permissionKeys: ['This permission is locked to its roles.'],
      })
    }
    return envelope({ role, permissionKeys: setRolePermissions(role.key, keys) })
  }),

  http.get('/api/v1/roles', async () => {
    await delay(200)
    if (!sessionCan(USERS_VIEW) && !sessionCan(MATRIX_VIEW)) return denied()
    return envelope(listRoles().filter((role) => role.assignable))
  }),
]
