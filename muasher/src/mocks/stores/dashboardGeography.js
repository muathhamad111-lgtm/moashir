import { regionsSeed } from '../fixtures/regionsSeed'
import { CITY_COORDINATES } from '../fixtures/cityCoordinates'

const PAYMENT_TOTAL = 1150
const AVERAGE_FLOOR = 58
const AVERAGE_SPREAD = 33
const PERCENT_DECIMALS = 2

// Entities registered per city now: a fixed spread that favours the larger cities.
const ENTITY_WEIGHTS = Object.freeze({
  'riyadh/riyadh': 9,
  'makkah/jeddah': 6,
  'eastern/dammam': 5,
  'makkah/makkah': 3,
  'madinah/madinah': 3,
  'eastern/ahsa': 2,
  'qassim/buraidah': 2,
  'asir/abha': 2,
  'riyadh/kharj': 1,
  'tabuk/tabuk': 1,
  'hail/hail': 1,
  'jazan/jazan': 1,
  'jouf/sakaka': 1,
})

function averageOf(key) {
  let hash = 11
  for (const char of key) hash = (hash * 31 + char.charCodeAt(0)) % 9973
  return (AVERAGE_FLOOR + (hash % AVERAGE_SPREAD) + (hash % 100) / 100).toFixed(PERCENT_DECIMALS)
}

// Spreads the period's requests over the cities in proportion to their entities.
function requestsByCity(requestsTotal) {
  const entities = Object.values(ENTITY_WEIGHTS).reduce((sum, count) => sum + count, 0)
  const shares = Object.entries(ENTITY_WEIGHTS).map(([key, count]) => [
    key,
    Math.floor((requestsTotal * count) / entities),
  ])
  const placed = shares.reduce((sum, [, count]) => sum + count, 0)
  if (shares.length) shares[0][1] += requestsTotal - placed
  return new Map(shares)
}

function figuresOf(entities, requests, average) {
  return {
    entities,
    requests,
    averagePercent: requests > 0 ? average : null,
    collected: (requests * PAYMENT_TOTAL).toFixed(PERCENT_DECIMALS),
  }
}

function cityRow(region, city, requests) {
  const key = `${region.code}/${city.code}`
  const [latitude, longitude] = CITY_COORDINATES[key] ?? [null, null]
  return {
    cityId: city.id,
    regionId: region.id,
    code: city.code,
    name: city.name,
    latitude,
    longitude,
    ...figuresOf(ENTITY_WEIGHTS[key] ?? 0, requests.get(key) ?? 0, averageOf(key)),
  }
}

function regionRow(region, cities) {
  const entities = cities.reduce((sum, city) => sum + city.entities, 0)
  const requests = cities.reduce((sum, city) => sum + city.requests, 0)
  return {
    regionId: region.id,
    code: region.code,
    name: region.name,
    ...figuresOf(entities, requests, averageOf(region.code)),
  }
}

// The overview map: entities now, requests / average / collections inside the period.
export function geographyFor(requestsTotal) {
  const requests = requestsByCity(requestsTotal)
  const byRegion = regionsSeed.map((region) => ({
    region,
    cities: region.cities.map((city) => cityRow(region, city, requests)),
  }))
  return {
    regions: byRegion.map(({ region, cities }) => regionRow(region, cities)),
    cities: byRegion.flatMap(({ cities }) => cities),
  }
}
