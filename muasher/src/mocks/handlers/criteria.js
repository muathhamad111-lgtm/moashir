import { http } from '../contract/http'
import { envelope, noContent } from '../contract/responses'
import {
  findMain,
  findPoint,
  findSub,
  findVersion,
  mainsOf,
  pointsOf,
  subsOf,
  versionWithStatus,
} from '../stores/criteriaStore'
import {
  mainResource,
  pointResource,
  subResource,
  versionSummary,
} from '../stores/criteriaSerializers'
import * as tree from '../stores/criteriaTree'
import { fieldMessage, reject } from '../stores/criteriaRejection'
import { CRITERIA_VERSION_STATUSES as STATUS } from '@/constants/criteria'
import {
  CRITERIA_PERMISSIONS as CAN,
  actorWith,
  criteriaRoute,
  found,
  paginate,
  readBody,
} from './criteriaSupport'
import { criteriaVersionHandlers } from './criteriaVersions'
import { modelCriteriaHandlers } from './modelCriteria'

const API = '/api/v1/criteria'
const SORTERS = {
  order: (main) => main.order,
  name: (main) => main.name.ar,
  weight: (main) => main.weight,
}

function listedVersion(query) {
  if (query.get('version')) return found(findVersion(query.get('version')))
  return versionWithStatus(STATUS.DRAFT) ?? versionWithStatus(STATUS.PUBLISHED)
}

function matches(main, term) {
  return [main.name.ar, main.name.en].some((name) => name.toLowerCase().includes(term))
}

function sortMains(mains, sort) {
  const descending = sort.startsWith('-')
  const read = SORTERS[sort.replace('-', '')] ?? SORTERS.order
  return [...mains].sort((left, right) => {
    const delta =
      read(left) > read(right) ? 1 : read(left) < read(right) ? -1 : left.order - right.order
    return descending ? -delta : delta
  })
}

function filteredMains(version, query) {
  const term = (query.get('search') ?? '').trim().toLowerCase()
  const active = query.get('filter[active]')
  return sortMains(mainsOf(version.id), query.get('sort') ?? 'order')
    .filter((main) => !term || matches(main, term))
    .filter((main) => active === null || active === '' || main.active === (active === 'true'))
}

function activeFlag(body) {
  if (typeof body.active !== 'boolean')
    throw reject.validation({ active: [fieldMessage('field.required')] })
  return body.active
}

function pointWithSub(point) {
  return { point: pointResource(point), sub: subResource(findSub(point.subId)) }
}

const mainHandlers = [
  http.get(
    `${API}/mains`,
    criteriaRoute(({ request }) => {
      actorWith(CAN.VIEW)
      const query = new URL(request.url).searchParams
      const version = listedVersion(query)
      if (!version)
        return envelope([], {
          version: null,
          pagination: { page: 1, perPage: 50, total: 0, lastPage: 1 },
        })
      const page = paginate(filteredMains(version, query), query)
      return envelope(page.items.map(mainResource), {
        version: versionSummary(version),
        pagination: page.pagination,
      })
    }),
  ),

  http.post(
    `${API}/mains`,
    criteriaRoute(async ({ request }) => {
      const actor = actorWith(CAN.MANAGE)
      return envelope(
        mainResource(tree.createMain(actor, await readBody(request))),
        {},
        { status: 201 },
      )
    }),
  ),

  http.post(
    `${API}/mains/reorder`,
    criteriaRoute(async ({ request }) => {
      const actor = actorWith(CAN.MANAGE)
      const body = await readBody(request)
      return envelope(tree.reorderMains(actor, body.orderedIds ?? []).map(mainResource))
    }),
  ),

  http.get(
    `${API}/mains/:mainId`,
    criteriaRoute(({ params }) => {
      actorWith(CAN.VIEW)
      const main = found(findMain(params.mainId))
      return envelope({ main: mainResource(main), subs: subsOf(main.id).map(subResource) })
    }),
  ),

  http.put(
    `${API}/mains/:mainId`,
    criteriaRoute(async ({ request, params }) => {
      const actor = actorWith(CAN.MANAGE)
      const main = found(findMain(params.mainId))
      return envelope(mainResource(tree.updateMain(actor, main, await readBody(request))))
    }),
  ),

  http.patch(
    `${API}/mains/:mainId/active`,
    criteriaRoute(async ({ request, params }) => {
      const actor = actorWith(CAN.MANAGE)
      const main = found(findMain(params.mainId))
      return envelope(
        mainResource(tree.toggleMain(actor, main, activeFlag(await readBody(request)))),
      )
    }),
  ),

  http.delete(
    `${API}/mains/:mainId`,
    criteriaRoute(({ params }) => {
      const actor = actorWith(CAN.MANAGE)
      tree.removeMain(actor, found(findMain(params.mainId)))
      return noContent()
    }),
  ),

  http.post(
    `${API}/mains/:mainId/subs/reorder`,
    criteriaRoute(async ({ request, params }) => {
      const actor = actorWith(CAN.MANAGE)
      const main = found(findMain(params.mainId))
      const body = await readBody(request)
      return envelope(tree.reorderSubs(actor, main, body.orderedIds ?? []).map(subResource))
    }),
  ),
]

const subHandlers = [
  http.post(
    `${API}/subs`,
    criteriaRoute(async ({ request }) => {
      const actor = actorWith(CAN.MANAGE)
      const body = await readBody(request)
      const main = found(findMain(body.mainCriterionId))
      return envelope(subResource(tree.createSub(actor, main, body)), {}, { status: 201 })
    }),
  ),

  http.get(
    `${API}/subs/:subId`,
    criteriaRoute(({ params }) => {
      actorWith(CAN.VIEW)
      const sub = found(findSub(params.subId))
      return envelope({ sub: subResource(sub), points: pointsOf(sub.id).map(pointResource) })
    }),
  ),

  http.put(
    `${API}/subs/:subId`,
    criteriaRoute(async ({ request, params }) => {
      const actor = actorWith(CAN.MANAGE)
      const sub = found(findSub(params.subId))
      return envelope(subResource(tree.updateSub(actor, sub, await readBody(request))))
    }),
  ),

  http.patch(
    `${API}/subs/:subId/active`,
    criteriaRoute(async ({ request, params }) => {
      const actor = actorWith(CAN.MANAGE)
      const sub = found(findSub(params.subId))
      return envelope(subResource(tree.toggleSub(actor, sub, activeFlag(await readBody(request)))))
    }),
  ),

  http.patch(
    `${API}/subs/:subId/evaluation-type`,
    criteriaRoute(async ({ request, params }) => {
      const actor = actorWith(CAN.MANAGE)
      const sub = found(findSub(params.subId))
      return envelope(subResource(tree.switchEvaluationType(actor, sub, await readBody(request))))
    }),
  ),

  http.delete(
    `${API}/subs/:subId`,
    criteriaRoute(({ params }) => {
      const actor = actorWith(CAN.MANAGE)
      tree.removeSub(actor, found(findSub(params.subId)))
      return noContent()
    }),
  ),

  http.post(
    `${API}/subs/:subId/points`,
    criteriaRoute(async ({ request, params }) => {
      const actor = actorWith(CAN.MANAGE)
      const sub = found(findSub(params.subId))
      return envelope(
        pointWithSub(tree.createPoint(actor, sub, await readBody(request))),
        {},
        { status: 201 },
      )
    }),
  ),

  http.post(
    `${API}/subs/:subId/points/reorder`,
    criteriaRoute(async ({ request, params }) => {
      const actor = actorWith(CAN.MANAGE)
      const sub = found(findSub(params.subId))
      const body = await readBody(request)
      return envelope(tree.reorderPoints(actor, sub, body.orderedIds ?? []).map(pointResource))
    }),
  ),
]

const pointHandlers = [
  http.put(
    `${API}/points/:pointId`,
    criteriaRoute(async ({ request, params }) => {
      const actor = actorWith(CAN.MANAGE)
      const point = found(findPoint(params.pointId))
      return envelope(pointWithSub(tree.updatePoint(actor, point, await readBody(request))))
    }),
  ),

  http.patch(
    `${API}/points/:pointId/active`,
    criteriaRoute(async ({ request, params }) => {
      const actor = actorWith(CAN.MANAGE)
      const point = found(findPoint(params.pointId))
      return envelope(
        pointResource(tree.togglePoint(actor, point, activeFlag(await readBody(request)))),
      )
    }),
  ),

  http.delete(
    `${API}/points/:pointId`,
    criteriaRoute(({ params }) => {
      const actor = actorWith(CAN.MANAGE)
      const sub = tree.removePoint(actor, found(findPoint(params.pointId)))
      return envelope({ sub: subResource(sub) })
    }),
  ),
]

export const criteriaHandlers = [
  ...criteriaVersionHandlers,
  ...mainHandlers,
  ...subHandlers,
  ...pointHandlers,
  ...modelCriteriaHandlers,
]
