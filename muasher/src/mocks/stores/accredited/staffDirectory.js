import { usersFixture } from '../../fixtures/users'
import { grantedPermissionKeys } from '../permissionsStore'

const SCORE_PERMISSION = 'assessment.score.manage'

function findStaff(id) {
  return (
    usersFixture.find((user) => user.id === Number(id) && !user.roles.includes('entity')) ?? null
  )
}

export function staffNameOf(id) {
  return findStaff(id)?.name ?? null
}

export function isActiveAssessor(id) {
  const staff = findStaff(id)
  return (
    Boolean(staff) &&
    staff.status === 'active' &&
    grantedPermissionKeys(staff.roles).includes(SCORE_PERMISSION)
  )
}

export function activeAssessors() {
  return usersFixture.filter((user) => isActiveAssessor(user.id))
}
