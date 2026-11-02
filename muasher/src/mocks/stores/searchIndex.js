import { allEntities, isDeleted, referenceOf } from './entitiesStore'
import { usersFixture } from '../fixtures/users'

const ARABIC_FOLDS = [
  [/[ً-ْٰـ]/g, ''],
  [/[أإآٱ]/g, 'ا'],
  [/ى/g, 'ي'],
  [/ة/g, 'ه'],
  [/ؤ/g, 'و'],
  [/ئ/g, 'ي'],
]

function toWesternDigits(text) {
  return text.replace(/[\u0660-\u0669\u06F0-\u06F9]/g, (digit) => String(digit.charCodeAt(0) % 16))
}

export function normalizeSearchText(value) {
  const text = toWesternDigits(String(value ?? '').normalize('NFKC').toLocaleLowerCase('en'))
  return ARABIC_FOLDS.reduce((folded, [pattern, replacement]) => folded.replace(pattern, replacement), text)
    .replace(/\s+/g, ' ')
    .trim()
}

export function searchableLength(value) {
  return normalizeSearchText(value).replace(/[^\p{L}\p{N}]/gu, '').length
}

function matches(needle, ...values) {
  return values.some((value) => normalizeSearchText(value).includes(needle))
}

function bilingual(value) {
  return [value?.ar, value?.en]
}

export function searchEntities(needle) {
  return allEntities()
    .filter((entity) => !isDeleted(entity))
    .filter((entity) => matches(needle, ...bilingual(entity.name), entity.owner.email))
    .map((entity) => ({
      id: entity.id,
      reference: referenceOf(entity),
      name: { ar: entity.name.ar, en: entity.name.en ?? null },
      status: entity.status,
    }))
}

export function searchUsers(needle) {
  return usersFixture
    .filter((user) => matches(needle, user.name, user.email))
    .map((user) => ({
      id: user.id,
      name: user.name,
      email: user.email,
      roles: [...(user.roles ?? [])],
      status: user.status,
    }))
}
