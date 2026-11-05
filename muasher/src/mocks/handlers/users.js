import { HttpResponse, delay } from 'msw'
import { http } from '../contract/http'
import { usersFixture } from '../fixtures/users'
import { failsRandomly } from '../contract/faults'

let users = usersFixture.map((item) => ({ ...item }))

let idSeed = 500000

function nextId() {
  idSeed += 1
  return idSeed
}

function envelope(data, meta = {}) {
  return HttpResponse.json({ data, meta, errors: null })
}

function errorResponse(message, status, fieldErrors = {}) {
  return HttpResponse.json({ message, errors: fieldErrors, code: status }, { status })
}

const maybeFail = failsRandomly(0.1)

function emailTaken(email, exceptId = null) {
  const needle = String(email).trim().toLowerCase()
  return users.some((user) => user.email.toLowerCase() === needle && user.id !== exceptId)
}

function statsOf(all) {
  const byRole = {}
  for (const user of all) {
    for (const role of user.roles ?? []) byRole[role] = (byRole[role] ?? 0) + 1
  }
  return {
    total: all.length,
    active: all.filter((user) => user.status === 'active').length,
    inactive: all.filter((user) => user.status === 'inactive').length,
    byRole,
  }
}

export const usersHandlers = [
  http.get('/api/v1/users', async ({ request }) => {
    await delay(420)
    if (maybeFail(0.08)) return errorResponse('Failed to load users', 503)
    const url = new URL(request.url)
    const search = (url.searchParams.get('search') ?? '').trim().toLowerCase()
    const roleFilter = url.searchParams.get('filter[role]')
    const statusFilter = url.searchParams.get('filter[status]')
    const sort = url.searchParams.get('sort') ?? 'name'
    const page = Number(url.searchParams.get('page')) || 1
    const perPage = Number(url.searchParams.get('perPage')) || 10

    let items = users.map((user) => ({ ...user }))
    if (search) {
      items = items.filter(
        (user) =>
          user.name.toLowerCase().includes(search) || user.email.toLowerCase().includes(search),
      )
    }
    if (roleFilter) items = items.filter((user) => (user.roles ?? []).includes(roleFilter))
    if (statusFilter) items = items.filter((user) => user.status === statusFilter)

    const desc = sort.startsWith('-')
    const key = desc ? sort.slice(1) : sort
    items.sort((a, b) => {
      const av = a[key] ?? ''
      const bv = b[key] ?? ''
      if (av < bv) return desc ? 1 : -1
      if (av > bv) return desc ? -1 : 1
      return 0
    })

    const total = items.length
    const lastPage = Math.max(1, Math.ceil(total / perPage))
    const start = (page - 1) * perPage
    const pageItems = items.slice(start, start + perPage)

    return envelope(pageItems, {
      pagination: { page, perPage: perPage, total, lastPage: lastPage },
      stats: statsOf(users),
    })
  }),

  http.get('/api/v1/users/:userId', async ({ params }) => {
    await delay(320)
    if (maybeFail(0.06)) return errorResponse('Failed to load the user', 503)
    const user = users.find((item) => item.id === Number(params.userId))
    if (!user) return errorResponse('User not found', 404)
    return envelope({ ...user })
  }),

  http.post('/api/v1/users', async ({ request }) => {
    await delay(440)
    if (maybeFail()) return errorResponse('Could not create the user', 500)
    const body = await request.json()
    if (emailTaken(body.email)) {
      return errorResponse('This email is already in use', 422, {
        email: ['This email is already in use.'],
      })
    }
    const created = {
      id: nextId(),
      name: body.name,
      email: body.email,
      roles: Array.isArray(body.roles) ? body.roles : [],
      status: body.status ?? 'active',
      lastLogin: null,
    }
    users.push(created)
    return envelope({ ...created })
  }),

  http.put('/api/v1/users/:userId', async ({ request, params }) => {
    await delay(400)
    if (maybeFail()) return errorResponse('Could not update the user', 500)
    const user = users.find((item) => item.id === Number(params.userId))
    if (!user) return errorResponse('User not found', 404)
    const body = await request.json()
    if (body.email !== undefined && emailTaken(body.email, user.id)) {
      return errorResponse('This email is already in use', 422, {
        email: ['This email is already in use.'],
      })
    }
    Object.assign(user, {
      name: body.name ?? user.name,
      email: body.email ?? user.email,
      roles: Array.isArray(body.roles) ? body.roles : user.roles,
      status: body.status ?? user.status,
    })
    return envelope({ ...user })
  }),

  http.patch('/api/v1/users/:userId/status', async ({ request, params }) => {
    await delay(280)
    if (maybeFail(0.08)) return errorResponse('Could not change the user status', 500)
    const user = users.find((item) => item.id === Number(params.userId))
    if (!user) return errorResponse('User not found', 404)
    const body = await request.json()
    user.status = body.status === 'active' ? 'active' : 'inactive'
    return envelope({ ...user })
  }),
]
