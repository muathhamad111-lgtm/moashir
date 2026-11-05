import { describe, expect, it } from 'vitest'
import { contentImageUrl } from '@/utils/contentImage'

describe('contentImageUrl', () => {
  it('reads both the mock string form and the API image object', () => {
    expect(contentImageUrl('/generated/hero.jpg')).toBe('/generated/hero.jpg')
    expect(contentImageUrl({ file_id: 'f1', url: '/api/v1/content/files/f1' })).toBe(
      '/api/v1/content/files/f1',
    )
  })

  it('returns an empty string when there is no image', () => {
    expect(contentImageUrl(null)).toBe('')
    expect(contentImageUrl('')).toBe('')
    expect(contentImageUrl({})).toBe('')
  })
})
