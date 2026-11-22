import { NOTIFICATION_TEXT_FIXTURES } from '../fixtures/notificationTexts'
import { NOTIFICATION_TYPE_CATALOG } from '../fixtures/notificationTypes'
import { smsSegments } from '@/utils/smsSegments'

const LOCALES = ['ar', 'en']
const PLACEHOLDER = /:([a-z][a-z0-9_]*)/gi
const PART_MAX = 2000
const SMS_MAX_SEGMENTS = 3
const CHANNEL_OF_LETTER = { I: 'in_app', E: 'email', S: 'sms' }
const SWITCHABLE = ['in_app', 'email', 'sms']
const AUDIENCE_KINDS = ['beneficiary', 'management']
const SMS = 'sms'
const IN_APP_PARTS = ['title', 'body', 'adminTitle', 'adminBody']
const FIXED_IN_CORE = ['action']

// Mirrors NotificationCode::isCore(): account access and security alerts.
export const CORE_NOTIFICATION_CODES = Object.freeze([
  'USR-01',
  'USR-02',
  'USR-04',
  'USR-05',
  'REG-02',
  'REG-07',
  'REG-08',
  'SEC-01',
  'SEC-02',
  'SEC-03',
  'SEC-04',
  'SEC-05',
  'SEC-06',
  'SEC-07',
  'SEC-08',
])

const MESSAGES = {
  required: { ar: 'أدخل النص بالعربية والإنجليزية.', en: 'Enter the text in Arabic and English.' },
  unknown: {
    ar: 'المتغير :{name} غير متاح لهذا الإشعار.',
    en: 'The variable :{name} is not available for this notification.',
  },
  core: {
    ar: 'الإشعار الأساسي يُرسل دائمًا، ولا يمكن إيقاف قنواته.',
    en: 'A core notification is always sent; its channels cannot be switched off.',
  },
  audience: {
    ar: 'هذا الإشعار لا يصل إلى هذه الفئة.',
    en: 'This notification does not reach that audience.',
  },
  kept: {
    ar: 'أبقِ المتغير :{name}؛ هذا الإشعار أساسي ويحتاجه.',
    en: 'Keep the variable :{name}; this core notification needs it.',
  },
  fixed: {
    ar: 'لا يمكن تغيير زر الإشعار الأساسي.',
    en: 'The button of a core notification cannot be changed.',
  },
  smsLong: {
    ar: 'الرسالة النصية طويلة: اجعلها في حدود 3 رسائل.',
    en: 'The text message is too long: keep it within 3 messages.',
  },
}

const overrides = new Map()
const channelChoices = new Map()

function say(key, locale, name = '') {
  return MESSAGES[key][locale === 'en' ? 'en' : 'ar'].replace('{name}', name)
}

function placeholdersIn(text) {
  return [
    ...new Set([...String(text).matchAll(PLACEHOLDER)].map((match) => match[1].toLowerCase())),
  ]
}

function genericTexts(code) {
  return [
    {
      key: `mock.${code}`,
      variant: '',
      parts: {
        title: { ar: `إشعار ${code}`, en: `Notification ${code}` },
        body: { ar: `نص إشعار ${code}.`, en: `The text of notification ${code}.` },
      },
    },
  ]
}

function templatesOf(code) {
  return NOTIFICATION_TEXT_FIXTURES[code] ?? genericTexts(code)
}

export function isCoreType(type) {
  return CORE_NOTIFICATION_CODES.includes(type.code)
}

export function audienceKindsOf(type) {
  const kinds = new Set(
    type.audiences.flatMap((audience) => {
      if (audience === 'entity') return ['beneficiary']
      return audience === 'account_holder' ? AUDIENCE_KINDS : ['management']
    }),
  )
  return AUDIENCE_KINDS.filter((kind) => kinds.has(kind))
}

function editableParts(template) {
  const { parts } = template
  if (parts.subject || !parts.title || !parts.body) return parts
  return { ...parts, emailSubject: parts.title, emailBody: parts.body }
}

function channelOfPart(part) {
  if (part === SMS) return SMS
  return IN_APP_PARTS.includes(part) ? 'in_app' : 'email'
}

function audienceOfText(type, variant) {
  const kinds = audienceKindsOf(type)
  if (kinds.length === 1) return kinds[0]
  if (!variant || !type.audiences.includes('entity')) return null
  return variant.split('.').pop().startsWith('entity') ? 'beneficiary' : 'management'
}

function variablesOf(template) {
  return [
    ...new Set(
      Object.values(template.parts).flatMap((text) =>
        LOCALES.flatMap((l) => placeholdersIn(text[l])),
      ),
    ),
  ]
}

export function findNotificationType(code) {
  return NOTIFICATION_TYPE_CATALOG.find((type) => type.code === code) ?? null
}

export function findTemplate(key) {
  for (const type of NOTIFICATION_TYPE_CATALOG) {
    const template = templatesOf(type.code).find((candidate) => candidate.key === key)
    if (template) return { type, template }
  }
  return null
}

function textResource(type, template) {
  const override = overrides.get(template.key)
  const isCore = isCoreType(type)
  const parts = Object.fromEntries(
    Object.entries(editableParts(template)).map(([part, value]) => [
      part,
      {
        default: { ...value },
        current: { ...(override?.parts[part] ?? value) },
        channel: channelOfPart(part),
        isFixed: isCore && FIXED_IN_CORE.includes(part),
      },
    ]),
  )
  const variables = variablesOf(template)
  return {
    key: template.key,
    variant: template.variant,
    audience: audienceOfText(type, template.variant),
    variables,
    requiredVariables: isCore ? variables : [],
    isCustomized: Boolean(override),
    updatedAt: override?.updatedAt ?? null,
    parts,
    sms: {
      current: override?.parts[SMS] ?? null,
      approval: override?.parts[SMS] ? { ar: 'not_required', en: 'not_required' } : null,
    },
  }
}

function shippedChannels(type) {
  return [...type.channels].map((letter) => CHANNEL_OF_LETTER[letter])
}

function isSwitchable(type, channel) {
  return !isCoreType(type) && SWITCHABLE.includes(channel)
}

function isEnabled(type, channel, audience) {
  const choice = isSwitchable(type, channel)
    ? channelChoices.get(`${type.code}|${channel}|${audience}`)
    : undefined
  return choice ?? shippedChannels(type).includes(channel)
}

export function enabledChannelsOf(type) {
  return Object.values(CHANNEL_OF_LETTER).filter((channel) =>
    audienceKindsOf(type).some((audience) => isEnabled(type, channel, audience)),
  )
}

export function channelStatesOf(type) {
  return Object.fromEntries(
    audienceKindsOf(type).map((audience) => [
      audience,
      Object.fromEntries(
        SWITCHABLE.map((channel) => [channel, isEnabled(type, channel, audience)]),
      ),
    ]),
  )
}

export function switchNotificationChannels(type, choices, audiences = audienceKindsOf(type)) {
  if (isCoreType(type)) return
  Object.entries(choices).forEach(([channel, enabled]) => {
    audiences.forEach((audience) => {
      const key = `${type.code}|${channel}|${audience}`
      if (enabled === shippedChannels(type).includes(channel)) channelChoices.delete(key)
      else channelChoices.set(key, enabled)
    })
  })
}

export function channelChoiceErrors(type, { channels, audience } = {}, language) {
  const unknown = Object.keys(channels ?? {}).some((channel) => !SWITCHABLE.includes(channel))
  if (!channels || unknown || isCoreType(type)) return { channels: [say('core', language)] }
  if (audience && !audienceKindsOf(type).includes(audience)) {
    return { audience: [say('audience', language)] }
  }
  return {}
}

export function notificationTypeResource(type) {
  return {
    code: type.code,
    category: type.category,
    isMandatory: type.isMandatory,
    isCore: isCoreType(type),
    audiences: [...type.audiences],
    audienceKinds: audienceKindsOf(type),
    channels: shippedChannels(type),
    channelSettings: audienceKindsOf(type).flatMap((audience) =>
      Object.values(CHANNEL_OF_LETTER).map((channel) => ({
        channel,
        audience,
        isEnabled: isEnabled(type, channel, audience),
        isShipped: shippedChannels(type).includes(channel),
        isSwitchable: isSwitchable(type, channel),
      })),
    ),
    texts: templatesOf(type.code).map((template) => textResource(type, template)),
  }
}

function partErrors(found, part, value, language) {
  const errors = {}
  const allowed = variablesOf(found.template)
  const isCore = isCoreType(found.type)
  LOCALES.forEach((locale) => {
    const text = String(value?.[locale] ?? '').trim()
    const path = `parts.${part}.${locale}`
    if (!text || text.length > PART_MAX) errors[path] = [say('required', language)]
    const unknown = placeholdersIn(text).filter((name) => !allowed.includes(name))
    const shipped = placeholdersIn(editableParts(found.template)[part]?.[locale] ?? '')
    const missing = isCore ? shipped.filter((name) => !placeholdersIn(text).includes(name)) : []
    if (unknown.length) errors[path] = [say('unknown', language, unknown[0])]
    if (missing.length) errors[path] = [say('kept', language, missing[0])]
    if (part === SMS && smsSegments(text).segments > SMS_MAX_SEGMENTS)
      errors[path] = [say('smsLong', language)]
  })
  return errors
}

function coreErrors(found, parts, language) {
  if (!isCoreType(found.type)) return {}
  return Object.keys(parts).reduce((errors, part) => {
    if (part === SMS) errors[`parts.${part}`] = [say('core', language)]
    if (FIXED_IN_CORE.includes(part)) errors[`parts.${part}`] = [say('fixed', language)]
    return errors
  }, {})
}

export function validateTextParts(found, parts, language) {
  const editable = editableParts(found.template)
  const unknownParts = Object.keys(parts ?? {}).filter(
    (part) => !(part in editable) && part !== SMS,
  )
  if (!parts || unknownParts.length) return { parts: [say('required', language)] }
  return Object.entries(parts).reduce(
    (errors, [part, value]) => ({ ...errors, ...partErrors(found, part, value, language) }),
    coreErrors(found, parts, language),
  )
}

export function saveNotificationText(found, parts, now) {
  overrides.set(found.template.key, { parts, updatedAt: now })
}

export function resetNotificationText(key) {
  overrides.delete(key)
}

export function resetNotificationTextsStore() {
  overrides.clear()
  channelChoices.clear()
}
