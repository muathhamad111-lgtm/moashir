import { describe, expect, it } from 'vitest'
import { scoredStationsOf } from '@/utils/assessments/scoredStations'
import { scorableUnits } from '@/utils/assessments/scoreSheet'
import { EVALUATION_TYPES } from '@/constants/criteria'

const MAINS = [
  {
    id: 1,
    name: { ar: 'القيادة', en: 'Leadership' },
    subs: [
      { id: 11, evaluationType: EVALUATION_TYPES.DIRECT, points: [] },
      {
        id: 12,
        evaluationType: EVALUATION_TYPES.GUIDING_POINTS,
        points: [{ id: 121 }, { id: 122 }],
      },
    ],
  },
  {
    id: 2,
    name: { ar: 'النتائج', en: 'Results' },
    subs: [{ id: 21, evaluationType: EVALUATION_TYPES.DIRECT, points: [] }],
  },
]

describe('scoredStationsOf', () => {
  const units = scorableUnits({ mains: MAINS })

  it('counts a sub-criterion as done only when every one of its items has a score', () => {
    const stations = scoredStationsOf(MAINS, units, {
      'sub-11': '8',
      'point-121': '6',
      'point-122': '',
    })
    expect(stations[0]).toMatchObject({
      id: 1,
      total: 2,
      complete: 1,
      scored: 2,
      scorable: 3,
      state: 'partial',
    })
    expect(stations[1]).toMatchObject({
      id: 2,
      complete: 0,
      scored: 0,
      scorable: 1,
      state: 'empty',
    })
  })

  it('marks a station complete once all its sub-criteria are scored', () => {
    const stations = scoredStationsOf(MAINS, units, {
      'sub-11': '8',
      'point-121': '6',
      'point-122': '7',
      'sub-21': '9',
    })
    expect(stations.map((station) => station.state)).toEqual(['complete', 'complete'])
  })

  it('marks a station partial as soon as one item is scored, before any sub-criterion is done', () => {
    const [first] = scoredStationsOf(MAINS, units, { 'point-121': '6' })
    expect(first).toMatchObject({ complete: 0, scored: 1, scorable: 3, state: 'partial' })
  })
})
