import { describe, expect, it } from 'vitest'
import { fileNameFrom } from '@/services/mappers/download'

const withDisposition = (value) => ({ 'content-disposition': value })

describe('fileNameFrom', () => {
  it('prefers the real UTF-8 name over the ASCII fallback the API sends first', () => {
    const headers = withDisposition("attachment; filename=download.pdf; filename*=utf-8''a.pdf")
    expect(fileNameFrom(headers, 'fallback.pdf')).toBe('a.pdf')
  })

  it('decodes an Arabic name with spaces', () => {
    const name = 'تقرير الحوكمة.pdf'
    const headers = withDisposition(
      `attachment; filename=download.pdf; filename*=UTF-8''${encodeURIComponent(name)}`,
    )
    expect(fileNameFrom(headers, 'fallback.pdf')).toBe(name)
  })

  it('reads a plain quoted or bare name when no encoded name is sent', () => {
    expect(fileNameFrom(withDisposition('attachment; filename="audit log.csv"'), 'x')).toBe(
      'audit log.csv',
    )
    expect(fileNameFrom(withDisposition('attachment; filename=users.csv'), 'x')).toBe('users.csv')
  })

  it('falls back to the plain name when the encoded name is malformed', () => {
    const headers = withDisposition(
      "attachment; filename=download.pdf; filename*=utf-8''%E0%A4%A.pdf",
    )
    expect(fileNameFrom(headers, 'fallback.pdf')).toBe('download.pdf')
  })

  it('uses the name the link carries when the header has none', () => {
    expect(fileNameFrom({}, 'feedback.pdf')).toBe('feedback.pdf')
    expect(fileNameFrom(withDisposition('attachment'), 'feedback.pdf')).toBe('feedback.pdf')
  })
})
