import { describe, expect, it } from 'vitest'
import { labelsChanged, matrixChanges, rolesHolding } from '@/utils/permissionMatrix'

const matrix = {
  system_admin: ['users.view', 'users.manage'],
  request_follower: ['requests.queue.view'],
  entity: [],
}

describe('permission matrix helpers', () => {
  it('lists the roles holding a key', () => {
    expect(rolesHolding(matrix, 'users.view')).toEqual(['system_admin'])
    expect(rolesHolding(matrix, 'unknown.key')).toEqual([])
  })

  it('computes full-replacement rows only for roles whose membership changes', () => {
    expect(
      matrixChanges(
        matrix,
        'users.view',
        ['request_follower'],
        ['system_admin', 'request_follower'],
      ),
    ).toEqual([
      { role: 'system_admin', keys: ['users.manage'] },
      { role: 'request_follower', keys: ['requests.queue.view', 'users.view'] },
    ])
    expect(
      matrixChanges(matrix, 'users.view', ['system_admin'], ['system_admin', 'request_follower']),
    ).toEqual([])
  })

  it('never touches roles that are not editable', () => {
    expect(
      matrixChanges(matrix, 'users.view', ['system_admin', 'entity'], ['system_admin']),
    ).toEqual([])
  })

  it('detects label changes in either language', () => {
    const permission = { label: { ar: 'أ', en: 'A' }, description: { ar: 'و', en: 'D' } }
    expect(
      labelsChanged(permission, { label: { ar: 'أ', en: 'A' }, description: { ar: 'و', en: 'D' } }),
    ).toBe(false)
    expect(
      labelsChanged(permission, { label: { ar: 'أ', en: 'B' }, description: { ar: 'و', en: 'D' } }),
    ).toBe(true)
  })
})
