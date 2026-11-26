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
    expect(keysOf(choice.leading.value)).toEqual(['number'])
    expect(keysOf(choice.trailing.value)).toEqual(['actions'])
    expect(choice.lockedDataCount.value).toBe(1)
  })

  it('reads the choice the account keeps, in its order', async () => {
    signedIn({ 'requests-queue': ['dueAt', 'status'] })
    const choice = useTableColumnChoice(ref('requests-queue'), COLUMNS)
    await flushPromises()
    expect(keysOf(choice.shown.value)).toEqual(['number', 'dueAt', 'status', 'actions'])
    expect(keysOf(choice.ordered.value)).toEqual([
      'dueAt',
      'status',
      'level',
      'assessor',
      'submittedAt',
    ])
  })

  it('applies a choice to this view only, without saving it', async () => {
    signedIn()
    const choice = useTableColumnChoice(ref('requests-queue'), COLUMNS)
    await flushPromises()
    choice.applyToView(['assessor', 'level'])
    expect(keysOf(choice.shown.value)).toEqual(['number', 'assessor', 'level', 'actions'])
    expect(choice.differsFromSaved.value).toBe(true)
    expect(profileService.updatePreferences).not.toHaveBeenCalled()
  })

  it('saves the order to the account, keeping the columns other views of the table have', async () => {
    signedIn()
    const choice = useTableColumnChoice(ref('requests-queue'), COLUMNS)
    await flushPromises()
    choice.applyToView(['status'])
    choice.saveAsDefault(['dueAt', 'level'])
    expect(profileService.updatePreferences).toHaveBeenLastCalledWith({
      // The advanced path's own columns (cycle, region) stay in the choice.
      tableColumns: { 'requests-queue': ['dueAt', 'level', 'cycle', 'region'] },
    })
    expect(keysOf(choice.shown.value)).toEqual(['number', 'dueAt', 'level', 'actions'])
    expect(choice.differsFromSaved.value).toBe(false)
  })

  it('keeps the table defaults as no choice, so later default changes still reach the viewer', async () => {
    signedIn()
    const choice = useTableColumnChoice(ref('requests-queue'), COLUMNS)
    await flushPromises()
    choice.saveAsDefault(['status'])
    choice.saveAsDefault(choice.defaults.value)
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
