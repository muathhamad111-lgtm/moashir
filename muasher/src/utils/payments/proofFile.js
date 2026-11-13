import { PROOF_RULES, PROOF_STATES } from '@/constants/bankTransfer'

const PDF_TYPE = 'application/pdf'
const IMAGE_PREFIX = 'image/'

function extensionOf(name) {
  const parts = String(name ?? '').split('.')
  return parts.length > 1 ? parts.pop().toLowerCase() : ''
}

export function proofIssue(file) {
  if (!file) return null
  if (!PROOF_RULES.EXTENSIONS.includes(extensionOf(file.name))) return PROOF_STATES.WRONG_TYPE
  if (file.type && !PROOF_RULES.MIME_TYPES.includes(file.type)) return PROOF_STATES.WRONG_TYPE
  if (file.size > PROOF_RULES.MAX_MB * PROOF_RULES.BYTES_PER_MB) return PROOF_STATES.TOO_LARGE
  return null
}

export function isPdfProof(proof) {
  return proof?.mimeType === PDF_TYPE
}

export function isImageProof(proof) {
  return String(proof?.mimeType ?? '').startsWith(IMAGE_PREFIX)
}
