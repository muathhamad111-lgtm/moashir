import { afterEach, describe, expect, it, vi } from 'vitest'
import { STORAGE_KEYS } from '@/constants/storageKeys'

const route = { query: {} }
const replace = vi.fn()
vi.mock('vue-router', () => ({ useRoute: () => route, useRouter: () => ({ replace }) }))

const { useNotificationCatalogueLayout } =
  await import('@/composables/useNotificationCatalogueLayout')

afterEach(() => {
  localStorage.clear()
  route.query = {}
  vi.clearAllMocks()
  vi.restoreAllMocks()
})

describe('useNotificationCatalogueLayout', () => {
  it('starts by notification when nothing was chosen', () => {
    expect(useNotificationCatalogueLayout().value).toBe('byType')
  })

  it('remembers the viewer choice and puts it in the link', () => {
    const layout = useNotificationCatalogueLayout()
    layout.value = 'byChannel'
    expect(localStorage.getItem(STORAGE_KEYS.notificationsLayout)).toBe('byChannel')
    expect(replace).toHaveBeenCalledWith({ query: { view: 'byChannel' } })
    expect(useNotificationCatalogueLayout().value).toBe('byChannel')
  })

  it('lets the link win and ignores an unknown stored value', () => {
    localStorage.setItem(STORAGE_KEYS.notificationsLayout, 'grid')
    expect(useNotificationCatalogueLayout().value).toBe('byType')
    route.query = { view: 'byChannel' }
    expect(useNotificationCatalogueLayout().value).toBe('byChannel')
  })

  it('falls back to by notification when storage is blocked', () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('blocked')
    })
    expect(useNotificationCatalogueLayout().value).toBe('byType')
  })
})
