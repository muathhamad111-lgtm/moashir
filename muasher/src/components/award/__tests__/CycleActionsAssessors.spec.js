import { afterEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestGlobals } from '@/test-utils'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import CycleActionsCard from '../CycleActionsCard.vue'

const CYCLE = {
  id: 31,
  name: { ar: 'دورة جائزة مؤشر', en: 'Moashir Award' },
  year: 2026,
  status: 'submission-open',
  canManage: true,
  revealsStaff: true,
  members: [],
  initialAssessors: [],
}

function render(cycle, locale = 'en') {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(sessionUserFor(['system_admin']))
  return mount(CycleActionsCard, {
    props: { cycle },
    global: { plugins: globals.plugins },
    attachTo: document.body,
  })
}

afterEach(() => {
  document.body.innerHTML = ''
})

describe('CycleActionsCard — initial assessors', () => {
  it('keeps a draft without a linked initial assessor closed and says why', () => {
    const draft = render({ ...CYCLE, status: 'draft' })
    expect(draft.text()).toContain('Link the initial assessors before opening')
    expect(draft.find('.btn--primary').attributes('disabled')).toBeDefined()
  })

  it.each(['submission-open', 'submission-closed'])(
    'warns that a %s cycle without a linked initial assessor cannot have its requests assigned',
    (status) => {
      expect(render({ ...CYCLE, status }).text()).toContain(
        'Requests of this cycle cannot be assigned',
      )
    },
  )

  it('says it in Arabic too', () => {
    expect(render(CYCLE, 'ar').text()).toContain('لا يمكن إسناد طلبات هذه الدورة')
  })

  it('stays quiet once an active initial assessor is linked, or for an inactive link only', () => {
    const linked = render({ ...CYCLE, initialAssessors: [{ id: 10, name: 'أ', isActive: true }] })
    expect(linked.text()).not.toContain('cannot be assigned')
    const inactive = render({
      ...CYCLE,
      initialAssessors: [{ id: 10, name: 'أ', isActive: false }],
    })
    expect(inactive.text()).toContain('cannot be assigned')
  })

  it('does not warn from the committee stage on', () => {
    expect(render({ ...CYCLE, status: 'committee-assessment' }).text()).not.toContain(
      'cannot be assigned',
    )
  })
})
