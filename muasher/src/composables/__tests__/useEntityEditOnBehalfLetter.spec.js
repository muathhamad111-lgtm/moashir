import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { flushPromises } from '@vue/test-utils'
import { withSetup } from '@/test-utils/withSetup'
import { useEntityEditOnBehalf } from '@/composables/useEntityEditOnBehalf'
import { entitiesService } from '@/services/entitiesService'

vi.mock('@/services/entitiesService', () => ({
  entitiesService: { updateOnBehalf: vi.fn() },
}))

const PERSON = 'عبدالله بن إبراهيم الزهراني'
const NEW_PERSON = 'ريم بنت خالد الحربي'
const ENTITY = {
  id: 7003,
  name: { ar: 'وقف النور', en: 'Al-Noor Endowment' },
  unifiedNumber: null,
  authorizedPersonName: PERSON,
  phone: null,
  entityType: 'waqf',
  developmentSector: 'social',
  orgSize: 'small',
  foundingYear: 2018,
  region: { id: 1 },
  city: { id: 1 },
  hasGovernanceGrade: false,
  governanceGrade: null,
  employeesCount: null,
  annualBeneficiariesCount: null,
  hasStrategicPlan: false,
}

const pdf = () => new File(['%PDF-1.4'], 'letter.pdf', { type: 'application/pdf' })

function setup() {
  const onSaved = vi.fn()
  const { api } = withSetup(() => useEntityEditOnBehalf({ entity: ref(ENTITY), onSaved }))
  api.start()
  api.form.setFieldValue('reason', 'Correction requested by the entity in writing.')
  return { api, onSaved }
}

async function submit(api) {
  await api.form.onSubmit()
  await flushPromises()
}

beforeEach(() => {
  vi.clearAllMocks()
  entitiesService.updateOnBehalf.mockResolvedValue({ data: {} })
})

describe('staff edit: authorization letter with a new authorized person', () => {
  it('needs no letter while the authorized person stays the same', async () => {
    const { api, onSaved } = setup()
    expect(api.personChanged.value).toBe(false)
    await submit(api)
    expect(onSaved).toHaveBeenCalled()
    expect(entitiesService.updateOnBehalf.mock.calls[0][1]).not.toHaveProperty(
      'authorizationLetter',
    )
  })

  it('asks for a new letter once the person changes and sends it with the edit', async () => {
    const { api } = setup()
    api.form.setFieldValue('authorizedPersonName', ` ${NEW_PERSON} `)
    await flushPromises()
    expect(api.personChanged.value).toBe(true)
    await submit(api)
    expect(entitiesService.updateOnBehalf).not.toHaveBeenCalled()
    expect(api.form.errors.value.authorizationLetter).toBe('Upload the document as a PDF.')
    const letter = pdf()
    api.form.setFieldValue('authorizationLetter', letter)
    await submit(api)
    expect(entitiesService.updateOnBehalf).toHaveBeenCalledWith(
      7003,
      expect.objectContaining({ authorizedPersonName: NEW_PERSON, authorizationLetter: letter }),
    )
  })

  it('refuses a letter that is not a PDF even without a change of person', async () => {
    const { api } = setup()
    api.form.setFieldValue(
      'authorizationLetter',
      new File(['png'], 'letter.png', { type: 'image/png' }),
    )
    await submit(api)
    expect(entitiesService.updateOnBehalf).not.toHaveBeenCalled()
    const { errors } = await api.form.validate()
    expect(errors.authorizationLetter).toBe('Upload the document as a PDF only.')
  })
})
