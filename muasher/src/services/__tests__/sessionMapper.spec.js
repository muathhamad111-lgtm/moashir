import { describe, expect, it } from 'vitest'
import { toSessionUser } from '@/services/mappers/session'
import { toUser } from '@/services/mappers/users'

describe('path access in the session and user mappers', () => {
  it('reads the qualifications and the request scope of the session', () => {
    const user = toSessionUser({
      id: 10,
      roles: ['assessor'],
      assessorQualifications: ['accredited'],
      requestScope: { allPaths: false, paths: ['accredited'] },
    })
    expect(user.assessorQualifications).toEqual(['accredited'])
    expect(user.requestScope).toEqual({ allPaths: false, paths: ['accredited'] })
    expect(toSessionUser({ id: 1 }).requestScope).toBeNull()
  })

  it('defaults a managed user to every path and no qualification', () => {
    expect(toUser({ id: 5 })).toMatchObject({
      assessorQualifications: [],
      requestScope: { allPaths: true, paths: [] },
    })
  })
})
