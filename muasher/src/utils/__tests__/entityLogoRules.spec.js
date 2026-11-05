import { describe, expect, it } from 'vitest'
import { logoDimensionIssue, logoFileIssue } from '@/utils/entityLogoRules'

describe('entity logo rules', () => {
  it('accepts PNG and JPEG up to 2 MB only', () => {
    expect(logoFileIssue({ type: 'image/png', size: 1024 })).toBeNull()
    expect(logoFileIssue({ type: 'image/jpeg', size: 2 * 1024 * 1024 })).toBeNull()
    expect(logoFileIssue({ type: 'image/svg+xml', size: 10 })).toBe('type')
    expect(logoFileIssue({ type: 'image/png', size: 2 * 1024 * 1024 + 1 })).toBe('size')
  })

  it('requires each side between 200 and 2000 pixels', () => {
    expect(logoDimensionIssue({ width: 400, height: 300 })).toBeNull()
    expect(logoDimensionIssue({ width: 150, height: 400 })).toBe('dimensions')
    expect(logoDimensionIssue({ width: 400, height: 2400 })).toBe('dimensions')
    expect(logoDimensionIssue(null)).toBe('type')
  })
})
