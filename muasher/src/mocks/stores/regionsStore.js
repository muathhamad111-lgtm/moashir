import { regionsSeed } from '../fixtures/regionsSeed'

let regions = []
let cities = []
let nextCityId = 1

function seedCities() {
  return regionsSeed.flatMap((region) =>
    region.cities.map((city) => ({ ...city, name: { ...city.name }, regionId: region.id, isActive: true })),
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
  nextCityId = Math.max(...cities.map((city) => city.id)) + 1
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

export function citiesOfRegion(regionId, { activeOnly = true } = {}) {
  return cities
    .filter((city) => city.regionId === Number(regionId) && (!activeOnly || city.isActive))
    .sort(bySortOrder)
}

export function activeRegions() {
  return regions.filter((region) => region.isActive)
}

export function allCities() {
  return [...cities].sort((left, right) => left.regionId - right.regionId || bySortOrder(left, right))
}

export function cityCodeTaken(regionId, code) {
  return cities.some((city) => city.regionId === Number(regionId) && city.code === code)
}

const CODE_MAX_LENGTH = 30

function codeFrom(englishName) {
  const base = englishName
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .slice(0, CODE_MAX_LENGTH)
  return base || 'city'
}

function generatedCode(regionId, englishName) {
  const base = codeFrom(englishName)
  let code = base
  for (let suffix = 2; cityCodeTaken(regionId, code); suffix += 1) code = `${base}_${suffix}`
  return code
}

export function createCity(regionId, { name, code, sortOrder }) {
  const siblings = citiesOfRegion(regionId, { activeOnly: false })
  const city = {
    id: nextCityId++,
    regionId: Number(regionId),
    code: code || generatedCode(regionId, name.en),
    name: { ar: name.ar, en: name.en },
    sortOrder: sortOrder ?? siblings.length + 1,
    isActive: true,
  }
  cities.push(city)
  return city
}

export function updateCity(city, { name, sortOrder }) {
  city.name = { ar: name.ar, en: name.en }
  if (sortOrder !== null && sortOrder !== undefined) city.sortOrder = sortOrder
  return city
}

export function setCityActive(city, isActive) {
  city.isActive = isActive
  return city
}
