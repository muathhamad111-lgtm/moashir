import { termsFixture } from '../fixtures/terms'

let current = null

export function resetTermsStore() {
  current = { terms: { ...termsFixture.terms }, privacy: { ...termsFixture.privacy } }
}

resetTermsStore()

export function currentTerms() {
  return current
}

export function withdrawTerms() {
  current = { terms: null, privacy: null }
}
