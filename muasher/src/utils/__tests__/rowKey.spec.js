import { describe, expect, it } from 'vitest'
import { reactive } from 'vue'
import { keepRowKey, rowKeyOf } from '@/utils/rowKey'

describe('rowKeyOf', () => {
  it('returns the same key for the same row on every call', () => {
    const row = { name: 'Plan' }
    expect(rowKeyOf(row)).toBe(rowKeyOf(row))
  })

  it('returns different keys for different rows with equal content', () => {
    expect(rowKeyOf({ name: 'Plan' })).not.toBe(rowKeyOf({ name: 'Plan' }))
  })

  it('returns the raw row key for its reactive proxy', () => {
    const row = { name: 'Plan' }
    const key = rowKeyOf(row)
    expect(rowKeyOf(reactive(row))).toBe(key)
  })

  it('keeps the key when a row is replaced by an edited copy', () => {
    const row = { name: 'Plan' }
    const key = rowKeyOf(row)
    const edited = keepRowKey(row, { ...row, name: 'Report' })
    expect(edited).toEqual({ name: 'Report' })
    expect(rowKeyOf(edited)).toBe(key)
  })
})
