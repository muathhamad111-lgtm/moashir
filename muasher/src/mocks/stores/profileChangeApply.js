import { PROFILE_CHANGE_FILES as F } from '@/constants/entityEnums'
import { PROFILE_CHANGE_FIELDS } from './profileChangeFields'

export const ENTITY_KEY_OF_FILE = Object.freeze({
  [F.LOGO]: 'logo',
  [F.VAT_CERTIFICATE]: 'vatCertificate',
  [F.LICENSE_CERTIFICATE]: 'licenseCertificate',
  [F.AUTHORIZATION_LETTER]: 'authorizationLetter',
})

function applyFile(entity, kind, change) {
  entity[ENTITY_KEY_OF_FILE[kind]] = change.upload ? { ...change.upload } : null
}

/** Writes the approved items of a change request onto the entity. */
export function applyApprovedItems(entity, request, approved) {
  Object.entries(request.proposed)
    .filter(([field]) => approved.includes(field))
    .forEach(([field, value]) => PROFILE_CHANGE_FIELDS[field].write(entity, value))
  Object.entries(request.files)
    .filter(([kind]) => approved.includes(kind))
    .forEach(([kind, change]) => applyFile(entity, kind, change))
}
