import { describe, expect, it, vi, beforeEach } from 'vitest'
import { searchService } from '@/services/searchService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const resource = [
  {
    type: 'entities',
    total: 7,
    items: [
      {
        id: 6002,
        name: { ar: 'جمعية إحسان الخيرية', en: 'Ehsan Charitable Society' },
        status: 'active',
      },
    ],
  },
  {
    type: 'requests',
    total: 1,
    items: [
      {
        id: 9003,
        number: 'SLF-2026-000003',
        path: 'self',
        status: 'result-available',
        isFrozen: false,
        entity: {
          id: 6002,
          reference: 'ENT-006002',
          name: { ar: 'جمعية إحسان الخيرية', en: 'Ehsan Charitable Society' },
          status: 'active',
        },
      },
    ],
  },
  {
    type: 'users',
    total: 1,
    items: [
      {
        id: 12,
        name: 'هند بنت علي الزهراني',
        email: 'hind.alzahrani@example.sa',
        roles: ['request_follower', 'system_admin', 'unknown_role'],
        status: 'active',
      },
    ],
  },
  { type: 'payments', total: 1, items: [{ id: 1 }] },
]

beforeEach(() => {
  vi.clearAllMocks()
})

describe('searchService', () => {
  it('calls GET /search with the q parameter only', async () => {
    apiClient.get.mockResolvedValue({ data: { data: [], meta: { limit: 5 }, errors: null } })
    await searchService.search('إحسان')
    expect(apiClient.get).toHaveBeenCalledWith('/search', { params: { q: 'إحسان' } })
  })

  it('maps the decoded grouped resource to the SPA shape and keeps the server order', async () => {
    apiClient.get.mockResolvedValue({ data: { data: resource, meta: {}, errors: null } })
    const { data } = await searchService.search('إحسان')
    expect(data.groups.map((group) => group.type)).toEqual(['entities', 'requests', 'users'])
    expect(data.groups[0]).toEqual({
      type: 'entities',
      total: 7,
      items: [
        {
          type: 'entities',
          key: 'entities:6002',
          id: 6002,
          title: { ar: 'جمعية إحسان الخيرية', en: 'Ehsan Charitable Society' },
          status: 'active',
        },
      ],
    })
    expect(data.groups[1].items[0]).toEqual({
      type: 'requests',
      key: 'requests:9003',
      id: 9003,
      title: { ar: 'جمعية إحسان الخيرية', en: 'Ehsan Charitable Society' },
      reference: 'SLF-2026-000003',
      path: 'self',
      pathLabel: null,
      status: 'result-available',
      isFrozen: false,
    })
    expect(data.groups[2].items[0]).toEqual({
      type: 'users',
      key: 'users:12',
      id: 12,
      title: 'هند بنت علي الزهراني',
      email: 'hind.alzahrani@example.sa',
      roles: ['request_follower', 'system_admin'],
      status: 'active',
    })
  })

  it('maps a user without an e-mail to an empty string', async () => {
    apiClient.get.mockResolvedValue({
      data: {
        data: [
          {
            type: 'users',
            total: 1,
            items: [{ id: 13, name: 'سارة القحطاني', roles: [], status: 'active' }],
          },
        ],
        meta: {},
        errors: null,
      },
    })
    const { data } = await searchService.search('سارة')
    expect(data.groups[0].items[0].email).toBe('')
  })

  it('never sends the query text to the logger, only its length', async () => {
    apiClient.get.mockResolvedValue({ data: { data: [], meta: { limit: 5 }, errors: null } })
    await searchService.search('noura.saad@example.sa')
    const context = logger.breadcrumb.mock.calls[0][2]
    expect(context).toEqual({ entity: 'global-search', queryLength: 21 })
    expect(JSON.stringify(logger.breadcrumb.mock.calls)).not.toContain('noura')
  })

  it('reports failures and rethrows', async () => {
    const failure = new Error('rate limited')
    apiClient.get.mockRejectedValue(failure)
    await expect(searchService.search('جمعية')).rejects.toBe(failure)
    expect(logger.error).toHaveBeenCalledWith(
      failure,
      expect.objectContaining({ action: 'search.global' }),
    )
  })
})
