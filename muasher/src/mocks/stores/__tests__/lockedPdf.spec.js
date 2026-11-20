import { describe, expect, it } from 'vitest'
import { isLockedPdf } from '@/mocks/stores/lockedPdf'

const bytesOf = (text) => new TextEncoder().encode(text)

describe('mock locked PDF detection', () => {
  it('treats a PDF with an encryption dictionary as locked', () => {
    expect(isLockedPdf(bytesOf('%PDF-1.7\ntrailer << /Root 1 0 R /Encrypt 5 0 R >>'))).toBe(true)
  })

  it('accepts a plain PDF and ignores non-PDF content', () => {
    expect(isLockedPdf(bytesOf('%PDF-1.4\n% evidence\n%%EOF\n'))).toBe(false)
    expect(isLockedPdf(bytesOf('PK\u0003\u0004 /Encrypt'))).toBe(false)
  })
})
