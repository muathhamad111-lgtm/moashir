import { describe, expect, it } from 'vitest'
import {
  attachmentRowErrors,
  attachmentsPayload,
  cloneAttachments,
} from '@/utils/criteriaAttachments'

function row(name, extra = {}) {
  return cloneAttachments([{ name, required: true, ...extra }])[0]
}

describe('criteriaAttachments', () => {
  it('sends empty details as null and the file limit as a number', () => {
    const [payload] = attachmentsPayload([
      row({ ar: 'الخطة', en: 'Plan' }, { allowedTypes: ['pdf'], maxFiles: 3 }),
    ])
    expect(payload).toEqual({
      name: { ar: 'الخطة', en: 'Plan' },
      guidance: null,
      required: true,
      allowedTypes: ['pdf'],
      maxFiles: 3,
    })
  })

  it('places each server error on the row that sent it, skipping blank rows', () => {
    const items = [row({ ar: '', en: '' }), row({ ar: 'الخطة', en: 'Plan' })]
    const errors = attachmentRowErrors(
      {
        'attachments[0].guidance.ar': 'Enter the Arabic guidance.',
        'attachments[0].allowedTypes[0]': 'Pick a known type.',
        'attachments.0.maxFiles': 'At most 50 files.',
      },
      items,
    )
    expect(errors[0].guidanceAr).toBe('')
    expect(errors[1]).toMatchObject({
      guidanceAr: 'Enter the Arabic guidance.',
      allowedTypes: 'Pick a known type.',
      maxFiles: 'At most 50 files.',
      nameAr: '',
    })
  })
})
