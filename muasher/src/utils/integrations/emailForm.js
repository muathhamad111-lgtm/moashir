import { keptSecret, withSecrets } from './secretValue'
import { changedValues, trimmedOrNull, writesEverything } from './partialPayload'

export function emailFormValues(resource) {
  return {
    active: Boolean(resource.active),
    mode: resource.mode,
    host: resource.host ?? '',
    port: resource.port ?? '',
    encryption: resource.encryption ?? '',
    username: resource.username ?? '',
    password: keptSecret(),
    fromAddress: resource.fromAddress ?? '',
    fromName: { ar: resource.fromName?.ar ?? '', en: resource.fromName?.en ?? '' },
    replyTo: resource.replyTo ?? '',
    ehloDomain: resource.ehloDomain ?? '',
    timeoutSeconds: resource.timeoutSeconds ?? '',
    testAddress: resource.testAddress ?? '',
  }
}

function fromNameOf(fromName) {
  const ar = fromName.ar.trim()
  const en = fromName.en.trim()
  return ar || en ? { ar, en } : null
}

function settingsOf(values) {
  return {
    active: values.active,
    mode: values.mode,
    host: trimmedOrNull(values.host),
    port: Number(values.port),
    encryption: values.encryption,
    username: trimmedOrNull(values.username),
    fromAddress: trimmedOrNull(values.fromAddress),
    fromName: fromNameOf(values.fromName),
    replyTo: trimmedOrNull(values.replyTo),
    ehloDomain: trimmedOrNull(values.ehloDomain),
    timeoutSeconds: Number(values.timeoutSeconds),
    testAddress: trimmedOrNull(values.testAddress),
  }
}

export function emailPayload(values, resource) {
  const changed = changedValues(settingsOf(values), settingsOf(emailFormValues(resource)), {
    full: writesEverything(resource),
  })
  return withSecrets(changed, { password: values.password })
}

export function emailSecretsChanged(payload) {
  return 'password' in payload
}
