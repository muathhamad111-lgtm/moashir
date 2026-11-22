import { regionsSeed } from '../fixtures/regionsSeed'

let regions = []
let cities = []

function seedCities() {
  return regionsSeed.flatMap((region) =>
    region.cities.map((city) => ({
      ...city,
      name: { ...city.name },
      regionId: region.id,
      isActive: true,
    })),
  )
}

export function resetRegionsStore() {
  regions = regionsSeed.map((region) => ({
    id: region.id,
    code: region.code,
    name: { ...region.name },
    isActive: true,
  }))
  cities = seedCities()
}

resetRegionsStore()

export function findRegion(id) {
  return regions.find((region) => region.id === Number(id)) ?? null
}

export function findCity(id) {
  return cities.find((city) => city.id === Number(id)) ?? null
}

export function activeCityOf(regionId, cityId) {
  const city = findCity(cityId)
  return city && city.isActive && city.regionId === Number(regionId) ? city : null
}

function bySortOrder(left, right) {
  return left.sortOrder - right.sortOrder || left.id - right.id
}

export function citiesOfRegion(regionId) {
  return cities
    .filter((city) => city.regionId === Number(regionId) && city.isActive)
    .sort(bySortOrder)
}

export function activeRegions() {
  return regions.filter((region) => region.isActive)
}
