import { NOTIFICATION_TYPE_CATALOG } from '../fixtures/notificationTypes'
import { MockRejection } from '../contract/rejection'

const ACCOUNT_HOLDER = 'account_holder'
const ASSESSOR = 'assessor'
const COMMITTEE_ASSESSOR = 'committee_assessor'
const OPTIONAL_CHANNELS = ['email', 'sms']
const CHANNEL_OF_LETTER = { I: 'in_app', E: 'email', S: 'sms' }

const choices = new Map()

function audiencesOf(viewer) {
  const roles = viewer.roles.flatMap((role) =>
    role === ASSESSOR ? [role, COMMITTEE_ASSESSOR] : [role],
  )
  return [ACCOUNT_HOLDER, ...roles]
}

function channelsOf(type) {
  return [...type.channels].map((letter) => CHANNEL_OF_LETTER[letter])
}

function configurable(type, channel) {
  return !type.isMandatory && OPTIONAL_CHANNELS.includes(channel)
}

function choiceKey(viewer, code, channel) {
  return `${viewer.id}|${code}|${channel}`
}

function receivableBy(viewer) {
  const audiences = audiencesOf(viewer)
  return NOTIFICATION_TYPE_CATALOG.filter((type) =>
    type.audiences.some((audience) => audiences.includes(audience)),
  )
}

function rowOf(viewer, type) {
  return {
    code: type.code,
    module: type.code.split('-')[0],
    category: type.category,
    isMandatory: type.isMandatory,
    channels: channelsOf(type).map((channel) => ({
      channel,
      enabled:
        !configurable(type, channel) ||
        choices.get(choiceKey(viewer, type.code, channel)) !== false,
      configurable: configurable(type, channel),
    })),
  }
}

export function preferencesOf(viewer) {
  return receivableBy(viewer).map((type) => rowOf(viewer, type))
}

function validChange(change) {
  return (
    change &&
    typeof change.code === 'string' &&
    OPTIONAL_CHANNELS.includes(change.channel) &&
    typeof change.enabled === 'boolean'
  )
}

export const PREFERENCE_REJECTIONS = Object.freeze({
  INVALID: 'validation_failed',
  NOT_APPLICABLE: 'notification_preference.not_applicable',
  LOCKED: 'notification_preference.locked',
})

function reject(code, field) {
  throw new MockRejection(422, code, { fields: { [field]: { key: code } } })
}

function verifiedChange(types, change, index) {
  if (!validChange(change)) reject(PREFERENCE_REJECTIONS.INVALID, `preferences.${index}`)
  const type = types.find((candidate) => candidate.code === change.code)
  if (!type || !channelsOf(type).includes(change.channel))
    reject(PREFERENCE_REJECTIONS.NOT_APPLICABLE, `preferences.${index}.code`)
  if (!configurable(type, change.channel))
    reject(PREFERENCE_REJECTIONS.LOCKED, `preferences.${index}.channel`)
  return change
}

export function updatePreferences(viewer, body) {
  const changes = Array.isArray(body?.preferences) ? body.preferences : []
  if (!changes.length) reject(PREFERENCE_REJECTIONS.INVALID, 'preferences')
  const types = receivableBy(viewer)
  changes
    .map((change, index) => verifiedChange(types, change, index))
    .forEach((change) =>
      choices.set(choiceKey(viewer, change.code, change.channel), change.enabled),
    )
  return preferencesOf(viewer)
}

export function resetNotificationPreferences() {
  choices.clear()
}
