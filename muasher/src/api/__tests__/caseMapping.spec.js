import { describe, expect, it } from 'vitest'
import {
  formDataFromWire,
  formDataToWire,
  fromWire,
  hasCanonicalValue,
  isBridgedEnumKey,
  isStatusKey,
  toCamelKey,
  toSnakeKey,
  toWire,
} from '@/api/caseMapping'

describe('caseMapping — the single camelCase ⇄ snake_case boundary', () => {
  it.each([
    ['officialEmail', 'official_email'],
    ['perPage', 'per_page'],
    ['isSuperAdmin', 'is_super_admin'],
    ['textOnLightHex', 'text_on_light_hex'],
    ['filter[recipientRole]', 'filter[recipient_role]'],
    ['id', 'id'],
  ])('converts %s to %s and back', (internal, wire) => {
    expect(toSnakeKey(internal)).toBe(wire)
    expect(toCamelKey(wire)).toBe(internal)
  })

  it.each(['REG-01', 'social-impact', 'users.view', 'ar', 'en', 'm1-s2', '5001', 'Content-Type'])(
    'leaves data-like key %s untouched in both directions',
    (key) => {
      expect(toSnakeKey(key)).toBe(key)
      expect(toCamelKey(key)).toBe(key)
    },
  )

  it('converts nested objects and arrays deeply', () => {
    const internal = {
      entityName: { ar: 'جمعية', en: 'Society' },
      items: [{ requestId: 1, assignedAssessor: { fullName: 'A' } }],
      meta: { pagination: { perPage: 15, lastPage: 3 } },
    }
    const wire = {
      entity_name: { ar: 'جمعية', en: 'Society' },
      items: [{ request_id: 1, assigned_assessor: { full_name: 'A' } }],
      meta: { pagination: { per_page: 15, last_page: 3 } },
    }
    expect(toWire(internal)).toEqual(wire)
    expect(fromWire(wire)).toEqual(internal)
  })

  it('maps status values between internal tokens and canonical values', () => {
    expect(toWire({ status: 'under-review', filteringStatus: 'awaiting-secretary-approval' })).toEqual({
      status: 'under_review',
      filtering_status: 'awaiting_secretary_approval',
    })
    expect(fromWire({ status: 'needs_completion', secretary_status: ['in_progress', 'approved'] })).toEqual({
      status: 'needs-completion',
      secretaryStatus: ['in-progress', 'approved'],
    })
    expect(toWire({ 'filter[status]': 'under-review' })).toEqual({ 'filter[status]': 'under_review' })
  })

  it('never rewrites values of non-status keys', () => {
    const internal = { slug: 'social-impact', code: 'csrf.token_mismatch', role: 'system_admin', token: 'brand_default' }
    expect(fromWire(toWire(internal))).toEqual(internal)
    expect(toWire(internal).slug).toBe('social-impact')
  })

  it('keeps the keys of opaque maps (role matrix) verbatim', () => {
    const wire = { matrix: { system_admin: ['users.view'], request_follower: [] } }
    expect(fromWire(wire)).toEqual(wire)
  })

  it('keeps the checkout metadata keys verbatim in both directions', () => {
    const wire = { checkout: { metadata: { moashir_payment_id: 'a', app_env: 'local' } } }
    expect(fromWire(wire).checkout.metadata).toEqual(wire.checkout.metadata)
    expect(toWire(fromWire(wire))).toEqual(wire)
  })

  it('converts validation field paths segment by segment', () => {
    expect(fromWire({ errors: { official_email: ['x'], 'items.0.amount_due': ['y'] } })).toEqual({
      errors: { officialEmail: ['x'], 'items.0.amountDue': ['y'] },
    })
  })

  it('passes primitives, null, files and dates through unchanged', () => {
    const file = new Blob(['a'])
    const date = new Date(0)
    expect(toWire(null)).toBeNull()
    expect(toWire('text')).toBe('text')
    expect(toWire(file)).toBe(file)
    expect(toWire(date)).toBe(date)
    expect(fromWire({ errors: null }).errors).toBeNull()
  })

  it('converts multipart field names', () => {
    const form = new FormData()
    form.append('logoVariant', 'dark')
    const wire = formDataToWire(form)
    expect([...wire.keys()]).toEqual(['logo_variant'])
    expect([...formDataFromWire(wire).keys()]).toEqual(['logoVariant'])
  })

  it('recognises the status family of keys', () => {
    expect(isStatusKey('status')).toBe(true)
    expect(isStatusKey('paymentStatus')).toBe(true)
    expect(isStatusKey('filter[status]')).toBe(true)
    expect(isStatusKey('statusLabel')).toBe(false)
  })

  it('bridges the criteria enum values (evaluation type, scale kind) like statuses', () => {
    const internal = { evaluationType: 'guiding-points', scaleKind: 'of-10', points: [{ scaleKind: 'relative' }] }
    const wire = { evaluation_type: 'guiding_points', scale_kind: 'of_10', points: [{ scale_kind: 'relative' }] }
    expect(toWire(internal)).toEqual(wire)
    expect(fromWire(wire)).toEqual(internal)
    expect(fromWire({ evaluation_type: 'direct' })).toEqual({ evaluationType: 'direct' })
  })

  it('leaves canonical values of other criteria enums untouched', () => {
    const wire = { group: 'creativity_learning', scope: 'reset_open', problem: 'weights_mismatch' }
    expect(fromWire(wire)).toEqual({ group: 'creativity_learning', scope: 'reset_open', problem: 'weights_mismatch' })
  })

  it('recognises the bridged enum keys', () => {
    expect(isBridgedEnumKey('evaluationType')).toBe(true)
    expect(isBridgedEnumKey('scaleKind')).toBe(true)
    expect(isBridgedEnumKey('group')).toBe(false)
    expect(hasCanonicalValue('status')).toBe(true)
    expect(hasCanonicalValue('scaleKind')).toBe(true)
    expect(hasCanonicalValue('scope')).toBe(false)
  })
})
