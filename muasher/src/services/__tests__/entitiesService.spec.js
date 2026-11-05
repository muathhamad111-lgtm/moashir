import { describe, expect, it, vi, beforeEach } from 'vitest'
import { entitiesService } from '@/services/entitiesService'
import { entityLifecycleService } from '@/services/entityLifecycleService'
import { entityEmailChangesService } from '@/services/entityEmailChangesService'
import { privacyRequestsService } from '@/services/privacyRequestsService'
import { regionsService } from '@/services/regionsService'
import { termsService } from '@/services/termsService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), put: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const envelope = { data: {}, meta: {}, errors: null }

beforeEach(() => {
  vi.clearAllMocks()
  for (const method of ['get', 'put', 'post', 'patch', 'delete'])
    apiClient[method].mockResolvedValue({ data: envelope })
})

describe('entitiesService — own entity', () => {
  it('reads and replaces the own profile and breadcrumbs only the field shape', async () => {
    await entitiesService.getMine()
    expect(apiClient.get).toHaveBeenCalledWith('/entities/me')
    const payload = { governanceGrade: '88.25', employeesCount: 50 }
    await entitiesService.updateMine(payload)
    expect(apiClient.put).toHaveBeenCalledWith('/entities/me', payload)
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'entities.updateMine',
      expect.objectContaining({ payloadShape: ['governanceGrade', 'employeesCount'] }),
    )
  })

  it('requests and confirms the official e-mail change', async () => {
    await entitiesService.requestEmailChange('office@albirr.example.sa')
    expect(apiClient.post).toHaveBeenCalledWith('/entities/me/email/change', {
      newEmail: 'office@albirr.example.sa',
    })
    await entitiesService.confirmEmailChange({ challengeId: 'c-1', code: '123456' })
    expect(apiClient.post).toHaveBeenCalledWith('/entities/me/email/confirm', {
      challengeId: 'c-1',
      code: '123456',
    })
  })

  it('treats 409, 422 and 429 as domain answers that are not reported as errors', async () => {
    const conflict = Object.assign(new Error('conflict'), { response: { status: 409 } })
    apiClient.post.mockRejectedValue(conflict)
    await expect(entitiesService.requestEmailChange('x@example.sa')).rejects.toBe(conflict)
    expect(logger.error).not.toHaveBeenCalled()
    const failure = new Error('network')
    apiClient.get.mockRejectedValue(failure)
    await expect(entitiesService.getMine()).rejects.toBe(failure)
    expect(logger.error).toHaveBeenCalledWith(
      failure,
      expect.objectContaining({ action: 'entities.getMine' }),
    )
  })
})

describe('entitiesService — staff', () => {
  it('lists with the filters, reads, edits on behalf and exports a CSV download', async () => {
    const params = { search: 'البر', 'filter[status]': 'active', page: 1 }
    await entitiesService.list(params)
    expect(apiClient.get).toHaveBeenCalledWith('/entities', { params })
    await entitiesService.get(5001)
    expect(apiClient.get).toHaveBeenCalledWith('/entities/5001')
    await entitiesService.updateOnBehalf(5001, { reason: 'Formal correction request' })
    expect(apiClient.put).toHaveBeenCalledWith('/entities/5001', {
      reason: 'Formal correction request',
    })
    const blob = new Blob(['csv'])
    apiClient.post.mockResolvedValue({
      data: blob,
      headers: {
        'content-disposition': 'attachment; filename=entities-20261101-090000.csv',
        'x-export-row-count': '7',
      },
    })
    const download = await entitiesService.exportCsv(params)
    expect(apiClient.post).toHaveBeenCalledWith('/entities/export', null, {
      params,
      responseType: 'blob',
    })
    expect(download).toEqual({ blob, fileName: 'entities-20261101-090000.csv', rowCount: 7 })
  })
})

describe('entityLifecycleService', () => {
  it('sends a reason with every state change and the acknowledged numbers with a deletion', async () => {
    await entityLifecycleService.deactivate(5001, 'Formal request from the board')
    expect(apiClient.post).toHaveBeenCalledWith('/entities/5001/deactivate', {
      reason: 'Formal request from the board',
    })
    await entityLifecycleService.reactivate(5001, 'Board request withdrawn')
    expect(apiClient.post).toHaveBeenCalledWith('/entities/5001/reactivate', {
      reason: 'Board request withdrawn',
    })
    await entityLifecycleService.restore(7002, 'Restored after the review')
    expect(apiClient.post).toHaveBeenCalledWith('/entities/7002/restore', {
      reason: 'Restored after the review',
    })
    await entityLifecycleService.resendVerification(7001)
    expect(apiClient.post).toHaveBeenCalledWith('/entities/7001/verification/resend', {})
    await entityLifecycleService.deletionImpact(6002)
    expect(apiClient.get).toHaveBeenCalledWith('/entities/6002/deletion-impact')
    await entityLifecycleService.softDelete(6002, {
      reason: 'Merged with another entity',
      acknowledgedRequestNumbers: ['ADV-2026-000031'],
    })
    expect(apiClient.delete).toHaveBeenCalledWith('/entities/6002', {
      data: {
        reason: 'Merged with another entity',
        confirmOpenRequests: true,
        acknowledgedRequestNumbers: ['ADV-2026-000031'],
      },
    })
    await entityLifecycleService.softDelete(6005, { reason: 'Merged with another entity' })
    expect(apiClient.delete).toHaveBeenLastCalledWith('/entities/6005', {
      data: {
        reason: 'Merged with another entity',
        confirmOpenRequests: false,
        acknowledgedRequestNumbers: [],
      },
    })
  })

  it('sends the typed reference and the disclaimer with a permanent deletion', async () => {
    const instruction = {
      reason: 'Approved erasure request of the entity',
      confirmation: 'ENT-007003',
      disclaimerAcknowledged: true,
    }
    await entityLifecycleService.permanentlyDelete(7003, instruction)
    expect(apiClient.delete).toHaveBeenCalledWith('/entities/7003/permanent', { data: instruction })
    await entityLifecycleService.exportPersonalData(6005, 'Access request number 41')
    expect(apiClient.post).toHaveBeenCalledWith('/entities/6005/export', {
      reason: 'Access request number 41',
    })
  })
})

describe('approval, privacy, regions and terms services', () => {
  it('reaches the real paths', async () => {
    await entityEmailChangesService.list({ 'filter[status]': 'awaiting-approval' })
    expect(apiClient.get).toHaveBeenCalledWith('/admin/entity-email-changes', {
      params: { 'filter[status]': 'awaiting-approval' },
    })
    await entityEmailChangesService.approve(301)
    expect(apiClient.post).toHaveBeenCalledWith('/admin/entity-email-changes/301/approve', {})
    await entityEmailChangesService.reject(301, 'The new address is not official')
    expect(apiClient.post).toHaveBeenCalledWith('/admin/entity-email-changes/301/reject', {
      reason: 'The new address is not official',
    })
    await privacyRequestsService.create({
      entityId: 6005,
      kind: 'access',
      details: 'Formal access request',
    })
    expect(apiClient.post).toHaveBeenCalledWith('/privacy/requests', {
      entityId: 6005,
      kind: 'access',
      details: 'Formal access request',
    })
    await privacyRequestsService.fulfil(41)
    expect(apiClient.post).toHaveBeenCalledWith('/privacy/requests/41/fulfil', {})
    await privacyRequestsService.reject(41, 'Outside the scope of the law')
    expect(apiClient.post).toHaveBeenCalledWith('/privacy/requests/41/reject', {
      reason: 'Outside the scope of the law',
    })
    await regionsService.listRegions()
    expect(apiClient.get).toHaveBeenCalledWith('/regions')
    await regionsService.createCity(7, { name: { ar: 'ضباء', en: 'Duba' } })
    expect(apiClient.post).toHaveBeenCalledWith('/regions/7/cities', {
      name: { ar: 'ضباء', en: 'Duba' },
    })
    await regionsService.setCityActive(69, false)
    expect(apiClient.patch).toHaveBeenCalledWith('/cities/69/active', { active: false })
    await termsService.current()
    expect(apiClient.get).toHaveBeenCalledWith('/terms/current')
  })
})

describe('entitiesService logo', () => {
  it('uploads the logo as multipart without logging the file name', async () => {
    apiClient.post.mockResolvedValue({ data: envelope })
    const file = new File(['png'], 'شعار-الجمعية.png', { type: 'image/png' })
    await entitiesService.uploadLogo(file)
    const [path, body] = apiClient.post.mock.calls[0]
    expect(path).toBe('/entities/me/logo')
    expect(body.get('file')).toBe(file)
    expect(JSON.stringify(logger.breadcrumb.mock.calls)).not.toContain('شعار')
  })

  it('removes the logo', async () => {
    apiClient.delete.mockResolvedValue({ data: envelope })
    await entitiesService.removeLogo()
    expect(apiClient.delete).toHaveBeenCalledWith('/entities/me/logo')
  })
})
