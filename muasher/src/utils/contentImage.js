import { API_ORIGIN } from '@/api/config'

const API_ASSET_PREFIX = '/api/'

function absoluteAssetUrl(url) {
  return API_ORIGIN && url.startsWith(API_ASSET_PREFIX) ? `${API_ORIGIN}${url}` : url
}

export function contentImageUrl(image) {
  if (!image) return ''
  const url = typeof image === 'string' ? image : (image.url ?? '')
  return url ? absoluteAssetUrl(url) : ''
}

export function contentImageId(image) {
  if (!image || typeof image === 'string') return null
  return image.fileId ?? image.file_id ?? null
}
