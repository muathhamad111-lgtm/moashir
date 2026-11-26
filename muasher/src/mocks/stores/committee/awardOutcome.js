import { rankingOf } from './committeeState'
import { placeOfRegion } from './committeeEntity'

export function awardOutcomeOf(record) {
  const ranking = rankingOf(record.id)
  return {
    status: record.status,
    region: placeOfRegion(record.regionId),
    regionalRank: ranking?.regionalRank ?? null,
    nationalRank: ranking?.nationalRank ?? null,
  }
}
