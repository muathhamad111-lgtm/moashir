import { describe, expect, it } from 'vitest'
import { completionStepOf, suggestedItemKeys } from '@/utils/accredited/completionForm'

const CRITERIA = {
  mains: [
    {
      id: 1,
      subs: [
        {
          id: 11,
          evaluationType: 'guiding-points',
          points: [{ id: 111 }, { id: 112 }],
        },
        { id: 12, evaluationType: 'direct', points: [] },
        { id: 13, evaluationType: 'direct', points: [] },
      ],
    },
  ],
}

describe('completion request suggestions', () => {
  it('suggests the evidence of each sub-criterion with an item not achieved or below 50%', () => {
    const scores = {
      subs: { 12: { percent: '49.99' }, 13: { percent: '50.00' } },
      points: { 111: { percent: '100.00' }, 112: { percent: '0.00' } },
    }
    expect(suggestedItemKeys(CRITERIA, scores)).toEqual(['11:evidence', '12:evidence'])
    expect(suggestedItemKeys(CRITERIA, null)).toEqual([])
  })

  it('sends an error back to the step that holds its field', () => {
    expect(completionStepOf('notes')).toBe('message')
    expect(completionStepOf('deadline')).toBe('message')
    expect(completionStepOf('items')).toBe('items')
    expect(completionStepOf('items.0.note')).toBe('items')
  })
})
