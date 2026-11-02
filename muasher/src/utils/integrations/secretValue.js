export const SECRET_ACTIONS = Object.freeze({ KEEP: 'keep', REPLACE: 'replace', CLEAR: 'clear' })

export function keptSecret() {
  return { action: SECRET_ACTIONS.KEEP, value: '' }
}

export function isSecretStored(meta) {
  return meta?.set === true
}

export function secretPatch(secret) {
  if (secret?.action === SECRET_ACTIONS.CLEAR) return { clear: true }
  if (secret?.action === SECRET_ACTIONS.REPLACE && secret.value) return secret.value
  return undefined
}

export function withSecrets(payload, secrets) {
  const patched = { ...payload }
  for (const [key, secret] of Object.entries(secrets)) {
    const patch = secretPatch(secret)
    if (patch !== undefined) patched[key] = patch
  }
  return patched
}
