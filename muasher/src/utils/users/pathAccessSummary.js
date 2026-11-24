import { MANAGED_PATHS, QUALIFICATION_PATHS } from '@/constants/paths'
import { isQualifiableUser, isScopableUser } from './pathAccessRoles'

export const PATH_ACCESS_ALL = 'all'
export const PATH_ACCESS_NOT_SET = 'notSet'

function followedPaths(user) {
  return isScopableUser(user) ? (user.requestScope?.paths ?? []) : []
}

function qualifiedPaths(user) {
  return isQualifiableUser(user)
    ? (user.assessorQualifications ?? []).map((value) => QUALIFICATION_PATHS[value])
    : []
}

// The paths a user works on, for one short line in the users list: a follower of every path reads
// «الكل»; otherwise the paths they follow and the paths they are qualified to assess. A role with
// no path access gets nothing (null); a role that has one but no path yet reads «غير محدد».
export function pathAccessSummary(user) {
  if (!isScopableUser(user) && !isQualifiableUser(user)) return null
  if (isScopableUser(user) && user.requestScope?.allPaths) return [PATH_ACCESS_ALL]
  const paths = new Set([...followedPaths(user), ...qualifiedPaths(user)])
  const ordered = MANAGED_PATHS.filter((path) => paths.has(path))
  return ordered.length ? ordered : [PATH_ACCESS_NOT_SET]
}
