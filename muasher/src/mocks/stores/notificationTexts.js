import { NOTIFICATION_TEXT_FIXTURES } from '../fixtures/notificationTexts'
import { NOTIFICATION_TYPE_CATALOG } from '../fixtures/notificationTypes'

const LOCALES = ['ar', 'en']
const PLACEHOLDER = /:([a-z][a-z0-9_]*)/gi
const PART_MAX = 2000
const CHANNEL_OF_LETTER = { I: 'in_app', E: 'email', S: 'sms' }

const MESSAGES = {
  required: { ar: 'أدخل النص بالعربية والإنجليزية.', en: 'Enter the text in Arabic and English.' },
  unknown: {
    ar: 'المتغير :{name} غير متاح لهذا الإشعار.',
    en: 'The variable :{name} is not available for this notification.',
  },
  locked: {
    ar: 'لا يمكن إيقاف قنوات الإشعار الإلزامي أو تشغيلها.',
    en: 'The channels of a mandatory notification cannot be switched off or on.',
  },
  kept: {
    ar: 'أبقِ المتغير :{name}؛ هذا الإشعار إلزامي ويحتاجه.',
    en: 'Keep the variable :{name}; this notification is mandatory and needs it.',
  },
}

const overrides = new Map()
const channelChoices = new Map()
const SWITCHABLE = ['in_app', 'email', 'sms']
const SMS = 'sms'

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
  const parts = Object.fromEntries(
    Object.entries(template.parts).map(([part, value]) => [
      part,
      { default: { ...value }, current: { ...(override?.parts[part] ?? value) } },
    ]),
  )
  const variables = variablesOf(template)
  return {
    key: template.key,
    variant: template.variant,
    variables,
    requiredVariables: type.isMandatory ? variables : [],
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
  return !type.isMandatory && SWITCHABLE.includes(channel)
}

function isEnabled(type, channel) {
  const choice = isSwitchable(type, channel)
    ? channelChoices.get(`${type.code}|${channel}`)
    : undefined
  return choice ?? shippedChannels(type).includes(channel)
}

export function enabledChannelsOf(type) {
  return Object.values(CHANNEL_OF_LETTER).filter((channel) => isEnabled(type, channel))
}

export function switchNotificationChannels(type, choices) {
  Object.entries(choices).forEach(([channel, enabled]) => {
    const key = `${type.code}|${channel}`
    if (enabled === shippedChannels(type).includes(channel)) channelChoices.delete(key)
    else channelChoices.set(key, enabled)
  })
}

export function channelChoiceErrors(type, choices, language) {
  const unknown = Object.keys(choices ?? {}).some((channel) => !SWITCHABLE.includes(channel))
  if (!choices || unknown || type.isMandatory) return { channels: [say('locked', language)] }
  return {}
}

export function notificationTypeResource(type) {
  return {
    code: type.code,
    category: type.category,
    isMandatory: type.isMandatory,
    channels: shippedChannels(type),
    channelSettings: Object.values(CHANNEL_OF_LETTER).map((channel) => ({
      channel,
      isEnabled: isEnabled(type, channel),
      isShipped: shippedChannels(type).includes(channel),
      isSwitchable: isSwitchable(type, channel),
    })),
    texts: templatesOf(type.code).map((template) => textResource(type, template)),
  }
}

function partErrors(found, part, value, language) {
  const errors = {}
  const allowed = variablesOf(found.template)
  LOCALES.forEach((locale) => {
    const text = String(value?.[locale] ?? '').trim()
    const path = `parts.${part}.${locale}`
    if (!text || text.length > PART_MAX) errors[path] = [say('required', language)]
    const unknown = placeholdersIn(text).filter((name) => !allowed.includes(name))
    const shipped = placeholdersIn(found.template.parts[part]?.[locale] ?? '')
    const missing = found.type.isMandatory
      ? shipped.filter((name) => !placeholdersIn(text).includes(name))
      : []
    if (unknown.length) errors[path] = [say('unknown', language, unknown[0])]
    if (missing.length) errors[path] = [say('kept', language, missing[0])]
  })
  return errors
}

export function validateTextParts(found, parts, language) {
  const allowed = (part) =>
    part in found.template.parts || (part === SMS && !found.type.isMandatory)
  const unknownParts = Object.keys(parts ?? {}).filter((part) => !allowed(part))
  if (!parts || unknownParts.length) return { parts: [say('required', language)] }
  return Object.entries(parts).reduce(
    (errors, [part, value]) => ({ ...errors, ...partErrors(found, part, value, language) }),
    {},
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
