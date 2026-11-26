import { NATIONAL_ID_STATES } from '@/constants/identityChanges'

// An account that must give a national ID opens no stamped file until the number is approved (2026-11-21).
export const FILE_ACCESS_REFUSAL_CODE = 'national_id.required_for_files'

// The account's national ID state when the server refused a file for it, otherwise null.
export function fileAccessRefusalOf(error) {
  if (error?.normalized?.code !== FILE_ACCESS_REFUSAL_CODE) return null
  return error.normalized.meta?.nationalIdStatus ?? NATIONAL_ID_STATES.MISSING
}
