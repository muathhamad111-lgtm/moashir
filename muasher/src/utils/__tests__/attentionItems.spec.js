import { describe, expect, it } from 'vitest'
import { attentionView, attentionViews } from '@/utils/attentionItems'

function item(key, count, context = {}, extra = {}) {
  return {
    key,
    count,
    severity: 'warning',
    link: '/assessment/accredited',
    dueAt: null,
    context: {
      assessmentId: null,
      paymentId: null,
      messageId: null,
      reference: null,
      remainingSubs: null,
      percent: null,
      ...context,
    },
    ...extra,
  }
}

describe('attention item views', () => {
  it('describes a single accredited draft with its remaining sub-criteria', () => {
    const view = attentionView(
      item('submit_accredited_draft', 1, { reference: 'ACC-2026-000014', remainingSubs: 4 }),
    )
    expect(view).toMatchObject({
      id: 'submit_accredited_draft',
      tone: 'warning',
      textKey: 'portal.strip.items.submit_accredited_draft.remaining',
      plural: 4,
      reference: 'ACC-2026-000014',
      to: '/assessment/accredited',
    })
  })

  it('falls back to the counted text when several drafts exist', () => {
    const view = attentionView(item('submit_accredited_draft', 2))
    expect(view.textKey).toBe('portal.strip.items.submit_accredited_draft.many')
    expect(view.plural).toBe(2)
  })

  it('shows the profile percent', () => {
    expect(attentionView(item('complete_profile', 1, { percent: 80 }))).toMatchObject({
      textKey: 'portal.strip.items.complete_profile.percent',
      percent: 80,
      tone: 'accent',
    })
    expect(attentionView(item('complete_profile', 1)).textKey).toBe(
      'portal.strip.items.complete_profile.plain',
    )
  })

  it('names a single reply by its reference and keeps the deep link', () => {
    const view = attentionView(
      item('read_contact_replies', 1, { reference: 'CNT-2026-000052' }, { link: '/messages/abc' }),
    )
    expect(view).toMatchObject({
      tone: 'info',
      textKey: 'portal.strip.items.read_contact_replies.single',
      to: '/messages/abc',
    })
  })

  it('drops unknown keys and external links', () => {
    const views = attentionViews([
      item('unknown_key', 1),
      item('complete_payment', 1, { reference: 'PAY-1' }, { link: 'https://example.sa' }),
    ])
    expect(views).toHaveLength(1)
    expect(views[0].to).toBeNull()
  })
})
