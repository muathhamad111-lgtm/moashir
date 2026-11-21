import { entityReject, fieldError } from './entityRejection'
import { signedDownloadOf, storeFile } from './finance/signedFiles'

const LOGO_TYPES = ['image/png', 'image/jpeg']
const LOGO_MAX_BYTES = 2 * 1024 * 1024

function refuse(code) {
  return entityReject.validation({ file: [fieldError(code)] })
}

export async function storeEntityLogo(user, entity, file) {
  if (!file || typeof file === 'string') throw refuse('field.required')
  if (!LOGO_TYPES.includes(file.type)) throw refuse('field.logo_type')
  if (file.size > LOGO_MAX_BYTES) throw refuse('field.logo_size')
  const stored = storeFile({
    purpose: 'entity_logo',
    name: file.name || 'logo.png',
    mimeType: file.type,
    sizeBytes: file.size,
    content: await file.arrayBuffer(),
  })
  entity.logo = {
    id: stored.id,
    url: signedDownloadOf(user, stored).url,
    uploadedAt: new Date().toISOString(),
  }
  return entity
}

export function removeEntityLogo(entity) {
  entity.logo = null
  return entity
}
