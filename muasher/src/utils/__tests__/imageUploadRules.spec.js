import { describe, expect, it } from 'vitest'
import { imageDimensionIssue, imageFileIssue } from '@/utils/imageUploadRules'
import { ACCOUNT_AVATAR_RULES } from '@/constants/accountAvatar'

function file(type, size = 10) {
  return { type, size }
}

describe('image upload rules', () => {
  it('names the first file issue against the given rules', () => {
    expect(imageFileIssue(file('image/gif'), ACCOUNT_AVATAR_RULES)).toBe('type')
    expect(
      imageFileIssue(file('image/png', ACCOUNT_AVATAR_RULES.maxBytes + 1), ACCOUNT_AVATAR_RULES),
    ).toBe('size')
    expect(imageFileIssue(file('image/jpeg'), ACCOUNT_AVATAR_RULES)).toBeNull()
  })

  it('checks both sides against the allowed range and treats unreadable images as the wrong type', () => {
    expect(imageDimensionIssue(null, ACCOUNT_AVATAR_RULES)).toBe('type')
    expect(imageDimensionIssue({ width: 199, height: 400 }, ACCOUNT_AVATAR_RULES)).toBe(
      'dimensions',
    )
    expect(imageDimensionIssue({ width: 400, height: 2001 }, ACCOUNT_AVATAR_RULES)).toBe(
      'dimensions',
    )
    expect(imageDimensionIssue({ width: 200, height: 2000 }, ACCOUNT_AVATAR_RULES)).toBeNull()
  })
})
