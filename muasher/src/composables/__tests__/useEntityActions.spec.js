import { describe, expect, it } from 'vitest'
import { defineComponent } from 'vue'
import { mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import { useAuthStore } from '@/stores/auth'
import { ENTITY_ACTIONS as A, useEntityActions } from '@/composables/useEntityActions'

function actionsFor(user, entity) {
  const globals = createTestGlobals({ locale: 'en' })
  let api
  mount(
    defineComponent({
      setup() {
        useAuthStore().user = { roles: [], permissions: [], committeeAppointments: [], ...user }
        api = useEntityActions()
        return () => null
      },
    }),
    { global: { plugins: globals.plugins } },
  )
  return api.actionsFor(entity).map((action) => action.key)
}

const ACTIVE = { id: 5001, status: 'active', isDeleted: false, entityType: 'civil_association' }
const ALL_ENTITY_KEYS = [
  'entities.view',
  'entities.manage',
  'entities.deactivate',
  'entities.reactivate',
  'entities.verification.resend',
  'entities.delete',
  'entities.export',
  'privacy.requests.manage',
]

describe('useEntityActions', () => {
  it('offers each action only with its own permission and the matching state', () => {
    const admin = { permissions: ALL_ENTITY_KEYS }
    expect(actionsFor(admin, ACTIVE)).toEqual([
      A.EDIT,
      A.DEACTIVATE,
      A.DELETE,
      A.EXPORT_PERSONAL_DATA,
    ])
    expect(actionsFor(admin, { ...ACTIVE, status: 'deactivated' })).toContain(A.REACTIVATE)
    expect(actionsFor(admin, { ...ACTIVE, status: 'pending-verification' })).toContain(
      A.RESEND_VERIFICATION,
    )
    expect(actionsFor(admin, { ...ACTIVE, isDeleted: true })).toEqual([
      A.RESTORE,
      A.EXPORT_PERSONAL_DATA,
    ])
  })

  it('keeps reactivation separate from deactivation and hides everything from a view-only role', () => {
    expect(
      actionsFor(
        { permissions: ['entities.view', 'entities.deactivate'] },
        { ...ACTIVE, status: 'deactivated' },
      ),
    ).toEqual([])
    expect(actionsFor({ permissions: ['entities.view'] }, ACTIVE)).toEqual([])
  })

  it('shows the permanent deletion to the Super Admin on deleted entities only', () => {
    const superAdmin = { isSuperAdmin: true, permissions: ALL_ENTITY_KEYS }
    expect(actionsFor(superAdmin, { ...ACTIVE, isDeleted: true })).toContain(A.PERMANENT_DELETE)
    expect(actionsFor(superAdmin, ACTIVE)).not.toContain(A.PERMANENT_DELETE)
    expect(
      actionsFor({ permissions: ALL_ENTITY_KEYS }, { ...ACTIVE, isDeleted: true }),
    ).not.toContain(A.PERMANENT_DELETE)
  })
})
