import { describe, expect, it } from 'vitest'
import {
  VERSION_TARGET,
  correctionAttachments,
  correctionFieldAliases,
  correctionValues,
  textCorrectionPayload,
} from '@/utils/criteriaTextCorrection'

const sub = {
  id: 101,
  name: { ar: 'التوجه الاستراتيجي', en: 'Strategic direction' },
  description: { ar: 'وصف', en: 'Description' },
  assessorNoteLabel: null,
  attachments: [{ id: 9, key: 'plan', name: { ar: 'الخطة', en: 'Plan' }, required: true }],
}

describe('criteriaTextCorrection', () => {
  it('reads the editable texts of each node type', () => {
    expect(correctionValues({ type: 'sub', node: sub })).toMatchObject({
      primaryEn: 'Strategic direction',
      noteAr: '',
    })
    expect(
      correctionValues({ type: 'point', node: { description: { ar: 'نقطة', en: 'Point' } } })
        .primaryEn,
    ).toBe('Point')
    expect(
      correctionValues({ type: VERSION_TARGET, node: { label: { ar: 'إصدار', en: 'Version' } } })
        .primaryAr,
    ).toBe('إصدار')
  })

  it('sends text fields only, plus the attachments whose names changed', () => {
    const attachments = correctionAttachments({ type: 'sub', node: sub })
    attachments[0].en = 'Approved plan'
    const payload = textCorrectionPayload(
      { type: 'sub', node: sub },
      {
        ...correctionValues({ type: 'sub', node: sub }),
        primaryEn: ' Strategic direction and vision ',
      },
      attachments,
      '  ',
    )
    expect(payload).toEqual({
      nodes: [
        {
          type: 'sub',
          id: 101,
          name: { ar: 'التوجه الاستراتيجي', en: 'Strategic direction and vision' },
          description: { ar: 'وصف', en: 'Description' },
          assessorNoteLabel: null,
        },
        { type: 'attachment', id: 9, name: { ar: 'الخطة', en: 'Approved plan' } },
      ],
      reason: null,
    })
    expect(JSON.stringify(payload)).not.toMatch(/weight|evaluation|active/)
  })

  it('builds the version label correction and maps server field paths back to the form', () => {
    const payload = textCorrectionPayload(
      { type: VERSION_TARGET, node: {} },
      { primaryAr: 'الإصدار', primaryEn: 'Edition' },
      [],
      'Correct the version name.',
    )
    expect(payload).toEqual({
      label: { ar: 'الإصدار', en: 'Edition' },
      reason: 'Correct the version name.',
    })
    expect(correctionFieldAliases({ type: 'point' })['nodes.0.description.en']).toBe('primaryEn')
    expect(correctionFieldAliases({ type: 'main' })['nodes.0.description.ar']).toBe('descriptionAr')
  })
})
