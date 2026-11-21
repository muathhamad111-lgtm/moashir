import { MockRejection } from '../contract/rejection'
import { nationalIdRequiredFor } from './authSettingsStore'
import { nationalIdStateOf } from './identityStore'

// Mirrors the API: an account that must give a national ID opens no assessment evidence until the number is
// approved. Transfer proofs, refund evidence and VAT certificates are not stamped and stay open to it.
const STAMPED_PURPOSES = Object.freeze(['assessment_evidence', 'evidence_bundle'])

export function assertMayOpenFile(user, purpose) {
  if (!user || !STAMPED_PURPOSES.includes(purpose)) return
  const required = nationalIdRequiredFor(user.roles ?? [], { superAdmin: user.superAdmin === true })
  const state = nationalIdStateOf(user.id, required)
  if (!required || state === 'provided') return
  throw new MockRejection(403, 'national_id.required_for_files', {
    meta: { nationalIdStatus: state },
  })
}
