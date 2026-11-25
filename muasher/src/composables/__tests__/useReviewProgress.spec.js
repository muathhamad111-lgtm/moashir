import { describe, expect, it } from 'vitest'
import { ref } from 'vue'
import { useReviewProgress } from '@/composables/useReviewProgress'
import { withSetup } from '@/test-utils/withSetup'

const SUB = {
  id: 21,
  code: 'M1.1',
  evaluationType: 'guiding-points',
  points: [{ id: 31 }, { id: 32 }],
}
const MAIN = { id: 11, name: { en: 'Leadership' }, subs: [SUB] }
const UNITS = [
  { level: 'point', id: 31 },
  { level: 'point', id: 32 },
]
const ROUND_TWO = {
  id: 7,
  round: 2,
  status: 'resubmitted',
  resolvedAt: '2026-11-19T10:30:00Z',
  items: [{ code: 'M1.1', kind: 'evidence', note: null }],
}
const ROUND_ONE = { round: 1, rawValue: '5.00', percent: '50.00' }

function progressWith(rounds) {
  const { api } = withSetup(() =>
    useReviewProgress({
      mains: ref([MAIN]),
      units: ref(UNITS),
      scoreSheet: { sheet: { values: { 'point-31': '5', 'point-32': '5' } } },
      steps: { position: ref(0) },
      rounds: ref(rounds),
    }),
  )
  return api
}

// Production report 2026-11-25: the assessor completed without re-scoring the items the entity
// completed. Those items count as remaining until scored again or kept as they are.
describe('review progress after a completion', () => {
  it('counts the items waiting for this round as remaining and names their sub-criteria', () => {
    const progress = progressWith({
      requests: [ROUND_TWO],
      units: { 'point-31': [ROUND_ONE], 'point-32': [ROUND_ONE, { ...ROUND_ONE, round: 2 }] },
    })
    expect(progress.missing.value).toBe(1)
    expect(progress.awaitingCodes.value).toEqual(['M1.1'])
  })

  it('leaves nothing remaining once the review is finished', () => {
    const progress = progressWith({
      requests: [ROUND_TWO],
      units: { 'point-31': [ROUND_ONE], 'point-32': [ROUND_ONE] },
      finished: true,
    })
    expect(progress.missing.value).toBe(0)
    expect(progress.awaitingCodes.value).toEqual([])
    expect(progress.stations.value[0].awaiting).toBe(0)
  })
})
