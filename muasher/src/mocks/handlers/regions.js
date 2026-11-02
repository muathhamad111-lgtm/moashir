import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { paginate } from '../contract/pagination'
import { actorWith, entityReject, entityRoute, readJson } from '../stores/entityRejection'
import { ENTITY_PERMISSIONS as P } from '../stores/entityAccess'
import { cityResource } from '../stores/entitySerializers'
import { rejectField } from '../stores/entityValidation'
import { normalizeSearchText } from '../stores/searchIndex'
import { currentTerms } from '../stores/termsStore'
import {
  activeRegions,
  allCities,
  citiesOfRegion,
  cityCodeTaken,
  createCity,
  findCity,
  findRegion,
  setCityActive,
  updateCity,
} from '../stores/regionsStore'

const CODE_PATTERN = /^[a-z0-9_]+$/

function regionResource(region) {
  return {
    id: region.id,
    code: region.code,
    name: { ...region.name },
    cities: citiesOfRegion(region.id).map((city) => ({ id: city.id, code: city.code, name: { ...city.name } })),
  }
}

function boundRegion(id) {
  const region = findRegion(id)
  if (!region) throw entityReject.notFound()
  return region
}

function boundCity(id) {
  const city = findCity(id)
  if (!city) throw entityReject.notFound()
  return city
}

function cityName(body) {
  const name = { ar: String(body.name?.ar ?? '').trim(), en: String(body.name?.en ?? '').trim() }
  if (!name.ar) rejectField('name.ar', 'field.required')
  if (!name.en) rejectField('name.en', 'field.required')
  return name
}

function sortOrderOf(body) {
  return body.sortOrder === null || body.sortOrder === undefined || body.sortOrder === '' ? null : Number(body.sortOrder)
}

function newCity(region, body) {
  const name = cityName(body)
  const code = body.code ? String(body.code) : null
  if (code && !CODE_PATTERN.test(code)) rejectField('code', 'field.invalid_choice')
  if (code && cityCodeTaken(region.id, code)) rejectField('code', 'field.city_code_taken')
  return { name, code, sortOrder: sortOrderOf(body) }
}

function cityFilters(query) {
  return {
    regionId: query.get('filter[regionId]'),
    active: query.get('filter[active]'),
    search: normalizeSearchText(query.get('search') ?? ''),
  }
}

function matchesCity(city, filters) {
  if (filters.regionId && city.regionId !== Number(filters.regionId)) return false
  if (filters.active && city.isActive !== (filters.active === 'true')) return false
  return !filters.search || [city.name.ar, city.name.en].some((value) => normalizeSearchText(value).includes(filters.search))
}

export const regionsHandlers = [
  http.get(
    '/api/v1/regions',
    entityRoute(() => envelope(activeRegions().map(regionResource))),
  ),

  http.get(
    '/api/v1/terms/current',
    entityRoute(() => envelope(currentTerms())),
  ),

  http.get(
    '/api/v1/cities',
    entityRoute(({ request }) => {
      actorWith(P.MANAGE)
      const query = new URL(request.url).searchParams
      const filters = cityFilters(query)
      const { items, pagination } = paginate(allCities().filter((city) => matchesCity(city, filters)), query, 50)
      return envelope(items.map(cityResource), { pagination })
    }),
  ),

  http.post(
    '/api/v1/regions/:id/cities',
    entityRoute(async ({ params, request }) => {
      actorWith(P.MANAGE)
      const region = boundRegion(params.id)
      const city = createCity(region.id, newCity(region, await readJson(request)))
      return envelope(cityResource(city), {}, { status: 201 })
    }),
  ),

  http.put(
    '/api/v1/cities/:id',
    entityRoute(async ({ params, request }) => {
      actorWith(P.MANAGE)
      const city = boundCity(params.id)
      const body = await readJson(request)
      return envelope(cityResource(updateCity(city, { name: cityName(body), sortOrder: sortOrderOf(body) })))
    }),
  ),

  http.patch(
    '/api/v1/cities/:id/active',
    entityRoute(async ({ params, request }) => {
      actorWith(P.MANAGE)
      const city = boundCity(params.id)
      const body = await readJson(request)
      if (typeof body.active !== 'boolean') rejectField('active', 'field.required')
      return envelope(cityResource(setCityActive(city, body.active)))
    }),
  ),
]
