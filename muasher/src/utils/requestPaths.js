import { QUEUE_STATUSES } from '@/constants/accredited'
import { ADVANCED_QUEUE_STATUSES } from '@/constants/advanced'
import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { QUALIFICATION_PATHS, REVIEWED_PATHS } from '@/constants/paths'
import { toRequestStatus } from '@/services/mappers/accreditedStatus'

const PATH_QUEUE_STATUSES = Object.freeze({
  [ASSESSMENT_PATHS.ACCREDITED]: QUEUE_STATUSES,
  [ASSESSMENT_PATHS.ADVANCED]: ADVANCED_QUEUE_STATUSES,
})
const OVERDUE_VALUES = ['true', 'false']

function ordered(paths) {
  return REVIEWED_PATHS.filter((path) => paths.includes(path))
}

export function followedPathsOf(user) {
  const scope = user?.requestScope
  if (!scope || scope.allPaths) return [...REVIEWED_PATHS]
  return ordered(scope.paths ?? [])
}

export function qualifiedPathsOf(user) {
  return ordered((user?.assessorQualifications ?? []).map((value) => QUALIFICATION_PATHS[value]))
}

export function assignedPathsOf(user, counts = []) {
  const known = new Set([...qualifiedPathsOf(user), ...counts.map((entry) => entry.path)])
  return REVIEWED_PATHS.filter((path) => known.has(path))
}

export function selectedPathOf(requested, available) {
  if (available.includes(requested)) return requested
  return available[0] ?? ASSESSMENT_PATHS.ACCREDITED
}

export function queueStatusesOf(path) {
  return PATH_QUEUE_STATUSES[path] ?? []
}

export function statusForPath(linkStatus, path) {
  if (typeof linkStatus !== 'string' || !linkStatus) return ''
  const statuses = queueStatusesOf(path)
  const tokens = linkStatus.split(',').map((token) => toRequestStatus(token.trim()))
  return tokens.find((token) => statuses.includes(token)) ?? ''
}

export function overdueFromLink(value) {
  return OVERDUE_VALUES.includes(value) ? value : ''
}

export function pathCountOf(counts, path) {
  return counts.find((entry) => entry.path === path) ?? null
}
