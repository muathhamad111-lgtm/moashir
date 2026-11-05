import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { flushPromises } from '@vue/test-utils'
import { withSetup } from '@/test-utils/withSetup'
import { useEntityEditOnBehalf } from '@/composables/useEntityEditOnBehalf'
import { useEntityDisplay } from '@/composables/useEntityDisplay'
import { entitiesService } from '@/services/entitiesService'

vi.mock('@/services/entitiesService', () => ({
  entitiesService: { updateOnBehalf: vi.fn() },
}))

const ENTITY = {
  id: 7003,
  name: { ar: 'وقف النور', en: 'Al-Noor Endowment' },
  unifiedNumber: null,
  authorizedPersonName: 'عبدالله بن إبراهيم الزهراني',
  phone: null,
  entityType: 'waqf',
  developmentSector: 'social',
  orgSize: 'small',
  foundingYear: 2018,
  region: { id: 1 },
  city: { id: 1 },
  governanceGrade: null,
  employeesCount: null,
  annualBeneficiariesCount: null,
  hasStrategicPlan: false,
}

const REASON = 'Correction requested by the entity in writing.'

function setup(entity) {
  const onSaved = vi.fn()
  const { api } = withSetup(() => useEntityEditOnBehalf({ entity: ref(entity), onSaved }))
  api.start()
  api.form.setFieldValue('reason', REASON)
  return { api, onSaved }
}

beforeEach(() => {
  vi.clearAllMocks()
  entitiesService.updateOnBehalf.mockResolvedValue({ data: {} })
})

describe('staff edit of the unified national number', () => {
  it('leaves an entity without the number unchanged when the staff member adds none', async () => {
    const { api, onSaved } = setup(ENTITY)
    expect(api.unifiedNumberRequired.value).toBe(false)
    await api.form.onSubmit()
    await flushPromises()
    expect(onSaved).toHaveBeenCalled()
    expect(entitiesService.updateOnBehalf.mock.calls[0][1]).not.toHaveProperty('unifiedNumber')
  })

  it('requires the number once the entity has one and sends the corrected value', async () => {
    const { api } = setup({ ...ENTITY, unifiedNumber: '7100007003' })
    expect(api.unifiedNumberRequired.value).toBe(true)
    api.form.setFieldValue('unifiedNumber', '')
    await api.form.onSubmit()
    await flushPromises()
    expect(entitiesService.updateOnBehalf).not.toHaveBeenCalled()
    api.form.setFieldValue('unifiedNumber', '7100007004')
    await api.form.onSubmit()
    await flushPromises()
    expect(entitiesService.updateOnBehalf).toHaveBeenCalledWith(
      7003,
      expect.objectContaining({ unifiedNumber: '7100007004', reason: REASON }),
    )
  })

  it('shows the number on the staff detail as an isolated left-to-right value', () => {
    const { api } = withSetup(() => useEntityDisplay())
    const [row] = api.rowsOf({ ...ENTITY, unifiedNumber: '7100007003' }, ['unifiedNumber'])
    expect(row).toMatchObject({
      key: 'unifiedNumber',
      label: 'Unified national number',
      value: '7100007003',
      ltr: true,
    })
    expect(api.rowsOf(ENTITY, ['unifiedNumber'])[0].value).toBe('Not provided')
  })
})
