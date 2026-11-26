import { describe, expect, it } from 'vitest'
import { nextSort, sortDirectionOf } from '@/utils/listSort'

describe('listSort', () => {
  it('reads the direction of a column from the sort value', () => {
    expect(sortDirectionOf('due_at', 'due_at')).toBe('asc')
    expect(sortDirectionOf('-due_at', 'due_at')).toBe('desc')
    expect(sortDirectionOf('-status', 'due_at')).toBeNull()
    expect(sortDirectionOf('', 'due_at')).toBeNull()
  })

  it('flips the same column and starts another column in its own first direction', () => {
    expect(nextSort('due_at', 'due_at')).toBe('-due_at')
    expect(nextSort('-due_at', 'due_at')).toBe('due_at')
    expect(nextSort('due_at', 'status')).toBe('status')
    expect(nextSort('due_at', 'assigned_at', 'desc')).toBe('-assigned_at')
    expect(nextSort('-assigned_at', 'assigned_at', 'desc')).toBe('assigned_at')
  })
})
