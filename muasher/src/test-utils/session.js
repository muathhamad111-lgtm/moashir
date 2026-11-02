import { defaultRoleMatrixFixture } from '@/mocks/fixtures/permissions'

export function permissionsForRoles(roles = []) {
  return [...new Set(roles.flatMap((role) => defaultRoleMatrixFixture[role] ?? []))]
}

export function sessionUserFor(roles = [], extra = {}) {
  return {
    id: 7001,
    name: 'مستخدم الاختبار',
    roles,
    permissions: permissionsForRoles(roles),
    isSuperAdmin: false,
    committeeAppointments: [],
    entityId: roles.includes('entity') ? 5001 : null,
    profileCompleted: true,
    status: 'active',
    ...extra,
  }
}
