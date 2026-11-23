import { MockRejection } from '../contract/rejection'
import { activeScenario } from './committee/committeeScenario'
import { NDA_DEFAULT_ROLES, ndaVersionsFixture } from '../fixtures/nda'
import { markdownBlocks } from '@/utils/legal/markdownBlocks'
import { usersFixture } from '../fixtures/users'

export const NDA_PENDING_SCENARIO = 'nda-pending'
const SEED_VERSION_ID = 501
const MANUAL_INTERVAL_MS = 24 * 60 * 60 * 1000
const MOCK_IP = '10.0.12.•'

const state = { roles: [], reminderDays: 3, acceptances: [], lastManualReminderAt: null }

export function resetNdaStore() {
  state.roles = [...NDA_DEFAULT_ROLES]
  state.reminderDays = 3
  state.acceptances = []
  state.lastManualReminderAt = null
}

resetNdaStore()

export function ndaScopeRoles() {
  return [...state.roles]
}

export function inNdaScope(user) {
  if (!user || user.entityId) return false
  return user.superAdmin === true || (user.roles ?? []).some((role) => state.roles.includes(role))
}

function seedAccepted(versionId) {
  return versionId === SEED_VERSION_ID && activeScenario() !== NDA_PENDING_SCENARIO
}

export function hasAcceptedNda(userId, versionId) {
  return (
    seedAccepted(versionId) ||
    state.acceptances.some((item) => item.userId === userId && item.versionId === versionId)
  )
}

export function ndaStatusOf(user, current) {
  if (!current || !inNdaScope(user)) return 'out_of_scope'
  return hasAcceptedNda(user.id, current.id) ? 'accepted' : 'pending'
}

export function recordNdaAcceptance(user, current, versionId) {
  if (!current || current.id !== Number(versionId))
    throw new MockRejection(422, 'nda.version_not_current')
  if (hasAcceptedNda(user.id, current.id)) return
  const roles = [...(user.superAdmin ? ['super_admin'] : []), ...(user.roles ?? [])]
  state.acceptances.push({
    userId: user.id,
    versionId: current.id,
    acceptedAt: new Date().toISOString(),
    roles,
  })
}

export function acceptancesOf(userId, versions) {
  return state.acceptances
    .filter((item) => item.userId === userId)
    .concat(
      seedAccepted(SEED_VERSION_ID)
        ? [
            {
              userId,
              versionId: SEED_VERSION_ID,
              acceptedAt: ndaVersionsFixture[0].publishedAt,
              roles: [],
            },
          ]
        : [],
    )
    .map((item) => ({
      id: `${item.userId}-${item.versionId}`,
      termsVersionId: item.versionId,
      version: versions.find((version) => version.id === item.versionId)?.version ?? '',
      acceptedAt: item.acceptedAt,
      acceptedRoles: item.roles,
      ipMasked: MOCK_IP,
    }))
    .sort((left, right) => String(right.acceptedAt).localeCompare(String(left.acceptedAt)))
}

export function ndaText(version) {
  return {
    id: version.id,
    version: version.version,
    title: version.title,
    publishedAt: version.publishedAt,
    body: { ar: markdownBlocks(version.body.ar), en: markdownBlocks(version.body.en) },
    changeSummary: version.changeSummary
      ? {
          ar: markdownBlocks(version.changeSummary.ar),
          en: markdownBlocks(version.changeSummary.en),
        }
      : null,
  }
}

export function staffInScope() {
  return usersFixture.filter(
    (user) => !user.entityId && inNdaScope({ ...user, roles: user.roles ?? [] }),
  )
}

export function ndaSettings() {
  return { roles: ndaScopeRoles(), reminderDays: state.reminderDays }
}

export function updateNdaSettings({ roles, reminderDays, reason }) {
  if (String(reason ?? '').trim().length < 10)
    throw new MockRejection(422, 'validation_failed', {
      fields: {
        reason: [
          {
            ar: 'اذكر سببًا من 10 أحرف على الأقل.',
            en: 'Give a reason of at least 10 characters.',
          },
        ],
      },
    })
  if (Array.isArray(roles)) state.roles = [...roles]
  if (Number.isInteger(reminderDays)) state.reminderDays = reminderDays
}

export function nextManualReminderAt() {
  if (!state.lastManualReminderAt) return null
  const next = state.lastManualReminderAt + MANUAL_INTERVAL_MS
  return next > Date.now() ? new Date(next).toISOString() : null
}

export function sendManualReminder() {
  if (nextManualReminderAt()) throw new MockRejection(429, 'nda.reminder_too_soon')
  state.lastManualReminderAt = Date.now()
}
