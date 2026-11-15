export const AVATAR_SOURCES = Object.freeze({
  photo: 'photo',
  entityLogo: 'entity_logo',
})

export const AVATAR_FALLBACK_ICONS = Object.freeze({
  person: 'user',
  entity: 'building',
})

export const ACCOUNT_AVATAR_RULES = Object.freeze({
  types: ['image/png', 'image/jpeg'],
  accept: '.png,.jpg,.jpeg,image/png,image/jpeg',
  maxBytes: 2 * 1024 * 1024,
  minSide: 200,
  maxSide: 2000,
})
