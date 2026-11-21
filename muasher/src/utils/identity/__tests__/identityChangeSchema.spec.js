import { describe, expect, it } from 'vitest'
import { identityChangePayload, identityChangeSchema } from '@/utils/identity/identityChangeSchema'

const t = (key) => key
const REASON = 'تصحيح خطأ إملائي في الاسم'

async function errorsOf(values) {
  try {
    await identityChangeSchema(t).validate(values, { abortEarly: false })
    return []
  } catch (error) {
    return error.errors
  }
}

describe('identity change form', () => {
  it('asks for a new name or a new national ID', async () => {
    expect(await errorsOf({ newName: '', newNationalId: '', reason: REASON })).toContain(
      'nationalIdentity.change.nothing',
    )
  })

  it('requires the current password only when the national ID changes', async () => {
    expect(await errorsOf({ newName: 'سلمان المطيري', reason: REASON })).toEqual([])
    expect(await errorsOf({ newNationalId: '1000000008', reason: REASON })).toContain(
      'nationalIdentity.change.passwordRequired',
    )
  })

  it('rejects an invalid national ID and a short reason', async () => {
    const errors = await errorsOf({
      newNationalId: '1000000009',
      reason: 'قصير',
      currentPassword: 'x',
    })
    expect(errors).toContain('nationalIdentity.field.invalid')
    expect(errors).toContain('nationalIdentity.change.reasonLength')
  })

  it('sends only what changes, with normalized digits', () => {
    expect(
      identityChangePayload({
        newName: ' ',
        newNationalId: '١٠٠٠٠٠٠٠٠٨',
        reason: ` ${REASON} `,
        currentPassword: 'secret-pass',
      }),
    ).toEqual({
      reason: REASON,
      newNationalId: '1000000008',
      currentPassword: 'secret-pass',
    })
    expect(identityChangePayload({ newName: 'سلمان', newNationalId: '', reason: REASON })).toEqual({
      reason: REASON,
      newName: 'سلمان',
    })
  })
})
