import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { entityRoute } from '../stores/entityRejection'
import { currentTerms } from '../stores/termsStore'
import { activeRegions, citiesOfRegion } from '../stores/regionsStore'

function regionResource(region) {
  return {
    id: region.id,
    code: region.code,
    name: { ...region.name },
    cities: citiesOfRegion(region.id).map((city) => ({
      id: city.id,
      code: city.code,
      name: { ...city.name },
    })),
  }
}

// The regions and cities are a fixed list (the settings screen left on 2026-11-22): only the public read remains.
export const regionsHandlers = [
  http.get(
    '/api/v1/regions',
    entityRoute(() => envelope(activeRegions().map(regionResource))),
  ),

  http.get(
    '/api/v1/terms/current',
    entityRoute(() => envelope(currentTerms())),
  ),
]
