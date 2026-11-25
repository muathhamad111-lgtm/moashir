import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { flushPromises } from '@vue/test-utils'
import { useTableColumnChoice } from '@/composables/useTableColumnChoice'
import { useAuthStore } from '@/stores/auth'
import { profileService } from '@/services/profileService'

vi.mock('@/services/profileService', () => ({
  profileService: {
    getPreferences: vi.fn(),
    updatePreferences: vi.fn(() => Promise.resolve({ data: {} })),
  },
}))

const COLUMNS = ref(
  ['number', 'level', 'status', 'assessor', 'submittedAt', 'dueAt', 'actions'].map((key) => ({
    key,
    label: key,
  })),
)
const keysOf = (columns) => columns.map((column) => column.key)

describe('a viewer column choice', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    vi.clearAllMocks()
  })

  function signedIn(tableColumns = {}) {
    profileService.getPreferences.mockResolvedValue({ data: { tableColumns } })
    useAuthStore().setUser({ id: 41, roles: ['request_follower'], permissions: [] })
  }

  it('starts with the table defaults and keeps the request and actions locked', async () => {
    signedIn()
    const choice = useTableColumnChoice(ref('requests-queue'), COLUMNS)
    await flushPromises()
    expect(keysOf(choice.shown.value)).toEqual([
      'number',
      'level',
      'status',
      'assessor',
      'dueAt',
      'actions',
    ])
    expect(keysOf(choice.locked.value)).toEqual(['number', 'actions'])
  })

  it('reads the choice the account keeps', async () => {
    signedIn({ 'requests-queue': ['status'] })
    const choice = useTableColumnChoice(ref('requests-queue'), COLUMNS)
    await flushPromises()
    expect(keysOf(choice.shown.value)).toEqual(['number', 'status', 'actions'])
  })

  it('saves a hidden column to the account and restores the defaults with null', async () => {
    signedIn()
    const choice = useTableColumnChoice(ref('requests-queue'), COLUMNS)
    await flushPromises()
    choice.toggle('level', false)
    expect(keysOf(choice.shown.value)).not.toContain('level')
    expect(profileService.updatePreferences).toHaveBeenLastCalledWith({
      // The advanced path's own columns (cycle, region) stay as they were.
      tableColumns: { 'requests-queue': ['cycle', 'region', 'status', 'assessor', 'dueAt'] },
    })
    choice.reset()
    expect(profileService.updatePreferences).toHaveBeenLastCalledWith({
      tableColumns: { 'requests-queue': null },
    })
    expect(keysOf(choice.shown.value)).toContain('level')
  })

  it('opens with the copy this browser keeps while the account answers', async () => {
    localStorage.setItem('muasher.tableColumns.41', JSON.stringify({ 'requests-queue': ['dueAt'] }))
    profileService.getPreferences.mockReturnValue(new Promise(() => {}))
    useAuthStore().setUser({ id: 41, roles: ['request_follower'], permissions: [] })
    const choice = useTableColumnChoice(ref('requests-queue'), COLUMNS)
    expect(keysOf(choice.shown.value)).toEqual(['number', 'dueAt', 'actions'])
  })
})
