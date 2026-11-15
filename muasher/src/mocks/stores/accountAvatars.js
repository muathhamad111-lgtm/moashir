import { findEntity } from './entitiesStore'
import { findFile } from './finance/financeState'
import { storedFileBody } from './finance/signedFiles'

const AVATAR_PATH = '/api/v1/me/avatar'
const PHOTO_TYPES = ['image/png', 'image/jpeg']
const PHOTO_MAX_BYTES = 2 * 1024 * 1024

const photos = new Map()

function linkOf(source, fileId) {
  return { source, url: `${AVATAR_PATH}?v=${fileId}` }
}

function entityLogoOf(descriptor) {
  return descriptor.entityId ? (findEntity(descriptor.entityId)?.logo ?? null) : null
}

export function isEntityAccount(descriptor) {
  return Boolean(descriptor.entityId) || descriptor.roles.includes('entity')
}

export function avatarOf(descriptor) {
  if (isEntityAccount(descriptor)) {
    const logo = entityLogoOf(descriptor)
    return logo ? linkOf('entity_logo', logo.id) : null
  }
  const photo = photos.get(descriptor.id)
  return photo ? linkOf('photo', photo.id) : null
}

export function photoIssue(file) {
  if (!file || typeof file === 'string') return 'The file field is required.'
  if (!PHOTO_TYPES.includes(file.type))
    return 'The file field must be a file of type: png, jpg, jpeg.'
  if (file.size > PHOTO_MAX_BYTES) return 'The file field must not be greater than 2048 kilobytes.'
  return null
}

export async function storePhoto(descriptor, file) {
  photos.set(descriptor.id, {
    id: crypto.randomUUID(),
    mimeType: file.type,
    content: await file.arrayBuffer(),
  })
  return avatarOf(descriptor)
}

export function removePhoto(descriptor) {
  photos.delete(descriptor.id)
}

export async function avatarImageOf(descriptor) {
  if (!isEntityAccount(descriptor)) {
    const photo = photos.get(descriptor.id)
    return photo ? { body: photo.content, mimeType: photo.mimeType } : null
  }
  const logo = entityLogoOf(descriptor)
  const file = logo ? findFile(logo.id) : null
  return file ? { body: await storedFileBody(file), mimeType: file.mimeType } : null
}

export function forgetPhotos() {
  photos.clear()
}
