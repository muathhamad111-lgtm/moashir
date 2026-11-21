import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { DISMISSED_NOTICES_KEY, useDismissedNotice } from '@/composables/useDismissedNotice'
import { logger } from '@/utils/logger'

vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

describe('useDismissedNotice', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
  })

  it('keeps a hidden notice hidden for the same viewer, and lets it come back', () => {
    useAuthStore().user = { id: 42 }
    const first = useDismissedNotice('criteria-move.9107.5')
    expect(first.dismissed.value).toBe(false)
    first.dismiss()
    expect(first.dismissed.value).toBe(true)
    expect(JSON.parse(localStorage.getItem(`${DISMISSED_NOTICES_KEY}.42`))).toEqual([
      'criteria-move.9107.5',
    ])
    expect(useDismissedNotice('criteria-move.9107.5').dismissed.value).toBe(true)
    expect(useDismissedNotice('criteria-move.9108.5').dismissed.value).toBe(false)
    first.restore()
    expect(useDismissedNotice('criteria-move.9107.5').dismissed.value).toBe(false)
  })

  it('shows the notice again when the stored list is unreadable', () => {
    useAuthStore().user = { id: 42 }
    localStorage.setItem(`${DISMISSED_NOTICES_KEY}.42`, '{not json')
    expect(useDismissedNotice('criteria-move.9107.5').dismissed.value).toBe(false)
    expect(logger.error).toHaveBeenCalled()
  })
})
