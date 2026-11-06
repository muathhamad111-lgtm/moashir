import { describe, expect, it } from 'vitest'
import {
  SCOPE_CHOICES,
  qualificationsPayload,
  qualificationsSchema,
  requestScopePayload,
  requestScopeSchema,
  requestScopeValuesOf,
} from '@/utils/users/pathAccessForms'
import { isQualifiableUser, isScopableUser } from '@/utils/users/pathAccessRoles'

const t = (key) => key

describe('user path access forms', () => {
  it('sends the qualifications with an optional reason', () => {
    expect(qualificationsPayload({ qualifications: ['accredited'], reason: '  ' })).toEqual({
      qualifications: ['accredited'],
    })
    expect(qualificationsPayload({ qualifications: [], reason: ' سبب التحديث المطلوب ' })).toEqual({
      qualifications: [],
      reason: 'سبب التحديث المطلوب',
    })
  })

  it('requires a reason from the Super Admin only', async () => {
    await expect(
      qualificationsSchema(t, false).validate({ qualifications: [], reason: '' }),
    ).resolves.toBeTruthy()
    await expect(
      qualificationsSchema(t, true).validate({ qualifications: [], reason: '' }),
    ).rejects.toThrow('validation.required')
    await expect(
      qualificationsSchema(t, false).validate({ qualifications: [], reason: 'قصير' }),
    ).rejects.toThrow('validation.minChars')
  })

  it('maps the scope to all paths or the selected paths in order', () => {
    expect(
      requestScopeValuesOf({ requestScope: { allPaths: true, paths: ['accredited'] } }),
    ).toEqual({
      scope: SCOPE_CHOICES.ALL,
      paths: [],
      reason: '',
    })
    expect(
      requestScopePayload({ scope: SCOPE_CHOICES.ALL, paths: ['advanced'], reason: '' }),
    ).toEqual({
      allPaths: true,
    })
    expect(
      requestScopePayload({
        scope: SCOPE_CHOICES.SELECTED,
        paths: ['advanced', 'accredited'],
        reason: '',
      }),
    ).toEqual({ allPaths: false, paths: ['accredited', 'advanced'] })
  })

  it('needs at least one path when the scope is limited', async () => {
    const schema = requestScopeSchema(t, false)
    await expect(schema.validate({ scope: 'selected', paths: [], reason: '' })).rejects.toThrow(
      'users.pathAccess.validation.pathRequired',
    )
    await expect(schema.validate({ scope: 'all', paths: [], reason: '' })).resolves.toBeTruthy()
  })

  it('offers the editors by role', () => {
    expect(isQualifiableUser({ roles: ['assessor'] })).toBe(true)
    expect(isScopableUser({ roles: ['system_admin'] })).toBe(true)
    expect(isScopableUser({ roles: ['finance_officer'] })).toBe(false)
  })
})
