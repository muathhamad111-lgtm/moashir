import { keptSecret, withSecrets } from './secretValue'
import {
  activeProviderChoice,
  activeProviderValue,
  changedValues,
  isSameValue,
  trimmedOrNull,
  withoutEmptyObjects,
  writesEverything,
} from './partialPayload'

function templateValues(template) {
  return { key: template.key, body: { ar: template.body?.ar ?? '', en: template.body?.en ?? '' } }
}

export function smsFormValues(resource) {
  const { taqnyat = {}, msegat = {} } = resource.providers ?? {}
  return {
    activeProvider: activeProviderChoice(resource.activeProvider),
    mode: resource.mode,
    testRecipients: [...(resource.testRecipients ?? [])],
    providers: {
      taqnyat: { bearerToken: keptSecret(), senderName: taqnyat.senderName ?? '' },
      msegat: {
        username: msegat.username ?? '',
        apiKey: keptSecret(),
        senderName: msegat.senderName ?? '',
        templateRestricted: msegat.templateRestricted === true,
      },
    },
    templates: (resource.templates ?? []).map(templateValues),
  }
}

function generalOf(values) {
  return { activeProvider: activeProviderValue(values.activeProvider), mode: values.mode, testRecipients: values.testRecipients }
}

function taqnyatOf(taqnyat) {
  return { senderName: trimmedOrNull(taqnyat.senderName) }
}

function msegatOf(msegat) {
  return { username: trimmedOrNull(msegat.username), senderName: trimmedOrNull(msegat.senderName), templateRestricted: msegat.templateRestricted }
}

function providersPayload(values, initial, full) {
  const { taqnyat, msegat } = values.providers
  return withoutEmptyObjects({
    taqnyat: withSecrets(changedValues(taqnyatOf(taqnyat), taqnyatOf(initial.providers.taqnyat), { full }), { bearerToken: taqnyat.bearerToken }),
    msegat: withSecrets(changedValues(msegatOf(msegat), msegatOf(initial.providers.msegat), { full }), { apiKey: msegat.apiKey }),
  })
}

function templatesChanged(values, initial) {
  return values.templates.some((template, index) => !isSameValue(template.body, initial.templates[index]?.body))
}

export function smsPayload(values, resource) {
  const full = writesEverything(resource)
  const initial = smsFormValues(resource)
  const payload = changedValues(generalOf(values), generalOf(initial), { full })
  const providers = providersPayload(values, initial, full)
  if (Object.keys(providers).length) payload.providers = providers
  if (full || templatesChanged(values, initial)) {
    payload.templates = values.templates.map((template) => ({ key: template.key, body: { ...template.body } }))
  }
  return payload
}

export function smsSecretsChanged(payload) {
  return Boolean(payload.providers?.taqnyat && 'bearerToken' in payload.providers.taqnyat) || Boolean(payload.providers?.msegat && 'apiKey' in payload.providers.msegat)
}

export function templateErrorPath(path, templates) {
  const match = /^templates\.([A-Za-z0-9_]+)\.body\.(ar|en)$/.exec(path)
  if (!match || /^\d+$/.test(match[1])) return path
  const index = templates.findIndex((template) => template.key === match[1] || camelKey(template.key) === match[1])
  return index === -1 ? path : `templates.${index}.body.${match[2]}`
}

function camelKey(key) {
  return key.replace(/_([a-z0-9])/g, (part, letter) => letter.toUpperCase())
}
