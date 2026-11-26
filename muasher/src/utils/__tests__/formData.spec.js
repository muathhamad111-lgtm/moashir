import { describe, expect, it } from 'vitest'
import { toFormData } from '@/utils/formData'
import { formDataToWire } from '@/api/caseMapping'

describe('toFormData', () => {
  it('sends nested names as bracketed keys, booleans as 1/0, null as empty and files as files', () => {
    const letter = new File(['%PDF-1.4'], 'letter.pdf', { type: 'application/pdf' })
    const form = toFormData({
      name: { ar: 'جمعية الوفاء', en: null },
      termsAccepted: true,
      hasStrategicPlan: false,
      governanceGrade: null,
      regionId: 2,
      authorizationLetter: letter,
    })
    expect(Object.fromEntries(form.entries())).toMatchObject({
      'name[ar]': 'جمعية الوفاء',
      'name[en]': '',
      termsAccepted: '1',
      hasStrategicPlan: '0',
      governanceGrade: '',
      regionId: '2',
    })
    expect(form.get('authorizationLetter').name).toBe('letter.pdf')
  })

  it('keeps the camel to snake mapping of the API client', () => {
    const wire = formDataToWire(toFormData({ licenseNumber: '1234', name: { ar: 'جمعية' } }))
    expect([...wire.keys()]).toEqual(['license_number', 'name[ar]'])
  })
})
