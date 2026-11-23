import { hasAcceptedNda, staffInScope } from './ndaStore'

const PER_PAGE = 15
const MOCK_IP = '10.0.12.•'

function rowOf(user, current) {
  const accepted = current ? hasAcceptedNda(user.id, current.id) : false
  return {
    user: { id: user.id, name: user.name, email: user.email, roles: [...user.roles] },
    status: accepted ? 'accepted' : 'pending',
    lastAcceptedVersion: accepted ? current.version : null,
    acceptedAt: accepted ? current.publishedAt : null,
    acceptedRoles: accepted ? [...user.roles] : [],
    ipMasked: accepted ? MOCK_IP : null,
  }
}

// The acceptance register over the fixture staff, filtered and paged like the API.
export function ndaRegister(current, params) {
  const all = current ? staffInScope().map((user) => rowOf(user, current)) : []
  const filter = params.get('filter') ?? 'all'
  const rows = filter === 'all' ? all : all.filter((row) => row.status === filter)
  const perPage = Number(params.get('per_page') ?? params.get('perPage') ?? PER_PAGE) || PER_PAGE
  const page = Math.max(1, Number(params.get('page') ?? 1))
  const accepted = all.filter((row) => row.status === 'accepted').length
  return {
    rows: rows.slice((page - 1) * perPage, page * perPage),
    meta: {
      summary: { inScope: all.length, accepted, pending: all.length - accepted },
      version: current ? { ...current, body: undefined } : null,
      pagination: {
        page,
        perPage,
        total: rows.length,
        lastPage: Math.max(1, Math.ceil(rows.length / perPage)),
      },
    },
  }
}
