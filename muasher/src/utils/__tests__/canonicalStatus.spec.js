import { describe, expect, it } from 'vitest'
import ar from '@/i18n/locales/ar.json'
import en from '@/i18n/locales/en.json'
import { STATUS_TOKENS } from '@/constants/statuses'
import { fromCanonicalStatus, statusLabelKey, toCanonicalStatus } from '@/utils/canonicalStatus'

describe('canonical status values', () => {
  it('maps internal tokens to canonical values and back', () => {
    expect(toCanonicalStatus('under-review')).toBe('under_review')
    expect(fromCanonicalStatus('under_review')).toBe('under-review')
    expect(toCanonicalStatus('paid')).toBe('paid')
    expect(toCanonicalStatus(null)).toBeNull()
  })

  it('resolves the label key by the canonical value for either form', () => {
    expect(statusLabelKey('needs-completion')).toBe('status.needs_completion')
    expect(statusLabelKey('needs_completion')).toBe('status.needs_completion')
  })

  it('has an ar and en label keyed by the canonical value for every status token', () => {
    const missing = STATUS_TOKENS.map(toCanonicalStatus).filter((key) => !ar.status[key] || !en.status[key])
    expect(missing).toEqual([])
    expect(Object.keys(en.status).some((key) => key.includes('-'))).toBe(false)
    expect(Object.keys(ar.status)).toEqual(Object.keys(en.status))
  })
})
