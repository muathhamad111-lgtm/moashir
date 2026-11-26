import { contentImageUrl } from '@/utils/contentImage'

export function toAvatar(raw) {
  if (!raw?.url) return null
  return { source: raw.source ?? null, url: contentImageUrl(raw.url) }
}
