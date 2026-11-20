import { notifyIdentityRequired } from '../sessionEvents'

export const NATIONAL_ID_REQUIRED_CODE = 'identity.national_id_required'
const FORBIDDEN = 403

export function reportIdentityRequired(error) {
  const response = error.response
  if (response?.status === FORBIDDEN && response.data?.code === NATIONAL_ID_REQUIRED_CODE) {
    notifyIdentityRequired()
  }
  throw error
}
