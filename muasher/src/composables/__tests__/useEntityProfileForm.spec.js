import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { flushPromises } from '@vue/test-utils'
import { withSetup } from '@/test-utils/withSetup'
import { useEntityProfileForm } from '@/composables/useEntityProfileForm'
import { useEntityLogo } from '@/composables/useEntityLogo'
import { useEntityStore } from '@/stores/entity'
import { entitiesService } from '@/services/entitiesService'

vi.mock('@/services/entitiesService', () => ({
  entitiesService: {
    updateMine: vi.fn(),
    uploadLogo: vi.fn(),
    removeLogo: vi.fn(),
    getMine: vi.fn(),
  },
}))
vi.mock('@/utils/imageDimensions', () => ({
  imageDimensionsOf: vi.fn().mockResolvedValue({ width: 400, height: 400 }),
}))

const PROFILE = {
  id: 6001,
  name: { ar: 'مؤسسة نماء للتنمية', en: 'Namaa Development Foundation' },
  status: 'active',
  authorizedPersonName: 'نورة بنت فهد العتيبي',
  phone: null,
  entityType: 'civil_foundation',
  developmentSector: 'educational',
  orgSize: 'medium',
  foundingYear: 2015,
  region: { id: 2 },
  city: { id: 12 },
  governanceGrade: null,
  employeesCount: null,
  annualBeneficiariesCount: null,
  hasStrategicPlan: true,
  licenseNumber: null,
  foundedOn: null,
  websiteUrl: null,
  marketingConsent: false,
  logo: null,
}

function setup(tab = 'basic') {
  return withSetup(() => {
    useEntityStore().profile = { ...PROFILE }
    return useEntityProfileForm(ref(tab))
  })
}

beforeEach(() => {
  vi.clearAllMocks()
  entitiesService.updateMine.mockImplementation((payload) =>
    Promise.resolve({ data: { ...PROFILE, ...payload, foundingYear: payload.foundingYear } }),
  )
})

describe('useEntityProfileForm', () => {
  it('saves the tab by merging it into the full profile body and lets the date set the year', async () => {
    const { api } = setup()
    api.form.setFieldValue('unifiedNumber', '7000006001')
    api.form.setFieldValue('licenseNumber', '000123')
    api.form.setFieldValue('foundedOn', '2012-03-01')
    await flushPromises()
    expect(api.form.values.foundingYear).toBe('2012')
    expect(api.dirtyFields.value).toEqual([
      'unifiedNumber',
      'licenseNumber',
      'foundedOn',
      'foundingYear',
    ])
    await api.form.onSubmit()
    await flushPromises()
    expect(entitiesService.updateMine).toHaveBeenCalledWith(
      expect.objectContaining({
        unifiedNumber: '7000006001',
        licenseNumber: '000123',
        foundedOn: '2012-03-01',
        foundingYear: 2012,
        authorizedPersonName: 'نورة بنت فهد العتيبي',
        websiteUrl: null,
        marketingConsent: false,
      }),
    )
    expect(api.isDirty.value).toBe(false)
  })

  it('requires the unified number on the basic tab only and never sends an empty one', async () => {
    const basic = setup()
    basic.api.form.setFieldValue('licenseNumber', '000123')
    await basic.api.form.onSubmit()
    await flushPromises()
    expect(entitiesService.updateMine).not.toHaveBeenCalled()
    expect(basic.api.form.errors.value.unifiedNumber).toBeTruthy()
    const contact = setup('contact')
    contact.api.form.setFieldValue('websiteUrl', 'https://www.namaa.example.sa')
    await contact.api.form.onSubmit()
    await flushPromises()
    expect(entitiesService.updateMine).toHaveBeenCalledTimes(1)
    expect(entitiesService.updateMine.mock.calls[0][0]).not.toHaveProperty('unifiedNumber')
  })

  it('keeps unsaved edits when the profile refreshes and resets on demand', async () => {
    const { api } = setup('contact')
    api.form.setFieldValue('websiteUrl', 'https://www.namaa.example.sa')
    await flushPromises()
    useEntityStore().profile = { ...PROFILE, logo: { id: 'l', url: '/x', uploadedAt: null } }
    await flushPromises()
    expect(api.form.values.websiteUrl).toBe('https://www.namaa.example.sa')
    api.reset()
    await flushPromises()
    expect(api.isDirty.value).toBe(false)
  })
})

describe('useEntityLogo', () => {
  it('refuses an SVG before any upload and applies an uploaded logo', async () => {
    const { api } = withSetup(() => {
      useEntityStore().profile = { ...PROFILE }
      return useEntityLogo()
    })
    await api.upload(new File(['<svg/>'], 'logo.svg', { type: 'image/svg+xml' }))
    expect(entitiesService.uploadLogo).not.toHaveBeenCalled()
    expect(api.error.value).toContain('PNG')
    entitiesService.uploadLogo.mockResolvedValue({
      data: { ...PROFILE, logo: { id: 'l1', url: '/api/v1/files/l1/download', uploadedAt: null } },
    })
    await api.upload(new File(['png'], 'logo.png', { type: 'image/png' }))
    expect(api.logo.value.id).toBe('l1')
    expect(api.error.value).toBe('')
  })

  it('shows the scan refusal sent by the server', async () => {
    const { api } = withSetup(() => {
      useEntityStore().profile = { ...PROFILE }
      return useEntityLogo()
    })
    entitiesService.uploadLogo.mockRejectedValue(
      Object.assign(new Error('422'), {
        normalized: {
          status: 422,
          code: 'file.infected',
          fieldErrors: {},
          message: 'File refused.',
        },
      }),
    )
    await api.upload(new File(['png'], 'logo.png', { type: 'image/png' }))
    expect(api.error.value).toBe('File refused.')
  })
})
