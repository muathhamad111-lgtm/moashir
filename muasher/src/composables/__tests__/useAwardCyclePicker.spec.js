import { beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { awardCycleService } from '@/services/awardCycleService'
import { useAwardCyclePicker } from '../useAwardCyclePicker'

vi.mock('@/services/awardCycleService', () => ({ awardCycleService: { list: vi.fn() } }))

const CYCLES = [
  { id: 30, status: 'closed' },
  { id: 31, status: 'committee-assessment' },
  { id: 32, status: 'draft' },
]

async function pickerOf(key) {
  let state
  mount(defineComponent({ setup: () => ((state = useAwardCyclePicker(key)), () => h('div')) }))
  await flushPromises()
  return state
}

beforeEach(() => {
  window.localStorage.clear()
  awardCycleService.list.mockResolvedValue({ data: CYCLES })
})

describe('useAwardCyclePicker', () => {
  it('names the one cycle in progress so the picker can open it directly', async () => {
    const picker = await pickerOf('filtering')
    expect(picker.cycles.value.map((cycle) => cycle.id)).toEqual([30, 31])
    expect(picker.openCycles.value.map((cycle) => cycle.id)).toEqual([31])
  })

  it('keeps drafts for the committee picker', async () => {
    const picker = await pickerOf('committee')
    expect(picker.openCycles.value.map((cycle) => cycle.id)).toEqual([31, 32])
  })

  it('lists the last cycle opened first, per picker', async () => {
    ;(await pickerOf('results')).remember(31)
    expect((await pickerOf('results')).cycles.value.map((cycle) => cycle.id)).toEqual([31, 30])
    expect((await pickerOf('secretary')).cycles.value.map((cycle) => cycle.id)).toEqual([30, 31])
  })
})
