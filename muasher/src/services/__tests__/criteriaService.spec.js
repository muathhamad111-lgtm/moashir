import { describe, expect, it, vi, beforeEach } from 'vitest'
import { criteriaService } from '@/services/criteriaService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const envelope = { data: {}, meta: {}, errors: null }

function rejection(status) {
  return Object.assign(new Error('rejected'), { response: { status } })
}

beforeEach(() => {
  vi.clearAllMocks()
  for (const method of ['get', 'post', 'put', 'patch', 'delete']) {
    apiClient[method].mockResolvedValue({ data: envelope })
  }
})

describe('criteriaService — versions', () => {
  it('lists versions with filter params and a params breadcrumb', async () => {
    const result = await criteriaService.listVersions({ 'filter[status]': 'archived' })
    expect(apiClient.get).toHaveBeenCalledWith('/criteria/versions', {
      params: { 'filter[status]': 'archived' },
    })
    expect(result).toEqual(envelope)
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'criteria.listVersions',
      expect.objectContaining({ entity: 'criteria-version', paramsShape: ['filter[status]'] }),
    )
  })

  it('creates a draft (clone of the published version by default)', async () => {
    await criteriaService.createDraft()
    expect(apiClient.post).toHaveBeenCalledWith('/criteria/versions', {})
  })

  it('discards a draft, checks it and reads one version', async () => {
    await criteriaService.discardDraft(3)
    await criteriaService.checkVersion(3)
    await criteriaService.getVersion(3)
    expect(apiClient.delete).toHaveBeenCalledWith('/criteria/versions/3')
    expect(apiClient.get).toHaveBeenCalledWith('/criteria/versions/3/check')
    expect(apiClient.get).toHaveBeenCalledWith('/criteria/versions/3')
  })

  it('publishes with the explicit confirmation, the scope and the reason', async () => {
    await criteriaService.publishVersion(4, {
      scope: 'reset_open',
      reason: 'Annual review of the weights.',
    })
    expect(apiClient.post).toHaveBeenCalledWith('/criteria/versions/4/publish', {
      confirm: true,
      scope: 'reset_open',
      reason: 'Annual review of the weights.',
    })
  })

  it('publishes the first version without a scope', async () => {
    await criteriaService.publishVersion(1)
    expect(apiClient.post).toHaveBeenCalledWith('/criteria/versions/1/publish', {
      confirm: true,
      scope: null,
      reason: null,
    })
  })

  it('sends text corrections to the labels endpoint', async () => {
    const payload = { nodes: [{ type: 'main', id: 1, name: { ar: 'القيادة', en: 'Leadership' } }] }
    await criteriaService.correctTexts(2, payload)
    expect(apiClient.patch).toHaveBeenCalledWith('/criteria/versions/2/labels', payload)
  })

  it('treats 409 and 422 as expected domain answers (no GlitchTip error)', async () => {
    apiClient.post.mockRejectedValueOnce(rejection(422))
    await expect(criteriaService.publishVersion(4, { scope: 'all' })).rejects.toThrow('rejected')
    apiClient.post.mockRejectedValueOnce(rejection(409))
    await expect(criteriaService.createDraft()).rejects.toThrow('rejected')
    expect(logger.error).not.toHaveBeenCalled()
  })

  it('reports unexpected failures to GlitchTip with the action context', async () => {
    apiClient.get.mockRejectedValueOnce(rejection(500))
    await expect(criteriaService.listVersions()).rejects.toThrow('rejected')
    expect(logger.error).toHaveBeenCalledWith(
      expect.any(Error),
      expect.objectContaining({ action: 'criteria.listVersions' }),
    )
  })
})

describe('criteriaService — tree', () => {
  it('creates a main criterion via POST', async () => {
    await criteriaService.createMain({ name: { ar: 'أ', en: 'A' }, weight: '1000.00' })
    expect(apiClient.post).toHaveBeenCalledWith('/criteria/mains', {
      name: { ar: 'أ', en: 'A' },
      weight: '1000.00',
    })
  })

  it('toggles active states via PATCH', async () => {
    await criteriaService.toggleMainActive(5, false)
    await criteriaService.toggleSubActive(51, true)
    await criteriaService.togglePointActive(511, false)
    expect(apiClient.patch).toHaveBeenCalledWith('/criteria/mains/5/active', { active: false })
    expect(apiClient.patch).toHaveBeenCalledWith('/criteria/subs/51/active', { active: true })
    expect(apiClient.patch).toHaveBeenCalledWith('/criteria/points/511/active', { active: false })
  })

  it('switches the evaluation type with the point-deletion confirmation flag', async () => {
    await criteriaService.switchEvaluationType(7, 'direct', { confirmDeletePoints: true })
    expect(apiClient.patch).toHaveBeenCalledWith('/criteria/subs/7/evaluation-type', {
      evaluationType: 'direct',
      confirmDeletePoints: true,
    })
    await criteriaService.switchEvaluationType(7, 'guiding-points')
    expect(apiClient.patch).toHaveBeenLastCalledWith('/criteria/subs/7/evaluation-type', {
      evaluationType: 'guiding-points',
      confirmDeletePoints: false,
    })
  })

  it('reorders mains, subs and points with a complete permutation', async () => {
    await criteriaService.reorderMains([2, 1])
    await criteriaService.reorderSubs(1, [12, 11])
    await criteriaService.reorderPoints(11, [112, 111])
    expect(apiClient.post).toHaveBeenCalledWith('/criteria/mains/reorder', { orderedIds: [2, 1] })
    expect(apiClient.post).toHaveBeenCalledWith('/criteria/mains/1/subs/reorder', {
      orderedIds: [12, 11],
    })
    expect(apiClient.post).toHaveBeenCalledWith('/criteria/subs/11/points/reorder', {
      orderedIds: [112, 111],
    })
  })

  it('creates and updates guiding points scoped to their sub-criterion', async () => {
    await criteriaService.createPoint(101, { weight: '100.00' })
    await criteriaService.updatePoint(1001, { weight: '50.00' })
    expect(apiClient.post).toHaveBeenCalledWith('/criteria/subs/101/points', { weight: '100.00' })
    expect(apiClient.put).toHaveBeenCalledWith('/criteria/points/1001', { weight: '50.00' })
  })

  it('removes nodes with DELETE', async () => {
    await criteriaService.removeMain(1)
    await criteriaService.removeSub(11)
    await criteriaService.removePoint(111)
    expect(apiClient.delete).toHaveBeenCalledWith('/criteria/mains/1')
    expect(apiClient.delete).toHaveBeenCalledWith('/criteria/subs/11')
    expect(apiClient.delete).toHaveBeenCalledWith('/criteria/points/111')
  })

  it('reads main and sub details', async () => {
    await criteriaService.getMain(1)
    await criteriaService.getSub(11)
    await criteriaService.listMains({ version: 2 })
    expect(apiClient.get).toHaveBeenCalledWith('/criteria/mains/1')
    expect(apiClient.get).toHaveBeenCalledWith('/criteria/subs/11')
    expect(apiClient.get).toHaveBeenCalledWith('/criteria/mains', { params: { version: 2 } })
  })
})
