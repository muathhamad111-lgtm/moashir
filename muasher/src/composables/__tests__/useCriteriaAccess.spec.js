import { describe, expect, it } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { useCriteriaAccess } from '@/composables/useCriteriaAccess'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

function accessFor(roles, status) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore().setUser(sessionUserFor(roles))
  let access
  mount(
    defineComponent({
      setup() {
        access = useCriteriaAccess(ref({ id: 2, status }))
        return () => h('div')
      },
    }),
    { global: globals },
  )
  return Object.fromEntries(Object.entries(access).map(([key, value]) => [key, value.value]))
}

describe('useCriteriaAccess', () => {
  it('lets the Criteria Officer edit and publish a draft', () => {
    expect(accessFor(['criteria_officer'], 'draft')).toMatchObject({
      canEditTree: true,
      canPublishDraft: true,
      canCorrectText: false,
    })
  })

  it('freezes published versions: text corrections only', () => {
    expect(accessFor(['criteria_officer'], 'published')).toMatchObject({
      canEditTree: false,
      canCorrectText: true,
      isFrozen: true,
    })
  })

  it('gives the System Administrator read and publish only', () => {
    expect(accessFor(['system_admin'], 'draft')).toMatchObject({
      canEditTree: false,
      canPublishDraft: true,
      canCorrectText: false,
    })
    expect(accessFor(['system_admin'], 'archived')).toMatchObject({
      canCorrectText: false,
      canManage: false,
    })
  })

  it('denies everything to a role without criteria permissions', () => {
    expect(accessFor(['finance_officer'], 'draft')).toMatchObject({
      canManage: false,
      canPublish: false,
      canEditTree: false,
    })
  })
})
