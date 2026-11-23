import { PROFILE_CHANGE_FILES as F } from '@/constants/entityEnums'
import { PROFILE_CHANGE_FIELDS } from './profileChangeFields'

function applyFile(entity, kind, change) {
  const file = change.upload ? { ...change.upload } : null
  if (kind === F.LOGO) entity.logo = file
  else entity.vatCertificate = file
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
