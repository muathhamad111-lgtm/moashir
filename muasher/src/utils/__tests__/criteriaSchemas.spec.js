import { describe, expect, it } from 'vitest'
import { object as yupObject } from 'yup'
import { pairedText, weightText } from '@/utils/criteriaSchemas'

const t = (key, params) => `${key}${params ? JSON.stringify(params) : ''}`

describe('criteriaSchemas', () => {
  it('accepts a bilingual pair filled on both sides or left empty, never half filled', () => {
    const schema = yupObject({ ar: pairedText(t, 'en'), en: pairedText(t, 'ar') })
    expect(schema.isValidSync({ ar: '', en: '' })).toBe(true)
    expect(schema.isValidSync({ ar: 'وصف', en: 'Description' })).toBe(true)
    expect(schema.isValidSync({ ar: '', en: 'Description' })).toBe(false)
  })

  it('validates two-decimal weights and blocks values above the remaining weight', () => {
    const schema = weightText(t, { max: '250.00' })
    expect(schema.isValidSync('250')).toBe(true)
    expect(schema.isValidSync('250.01')).toBe(false)
    expect(schema.isValidSync('12.345')).toBe(false)
    expect(schema.isValidSync('0')).toBe(false)
    expect(weightText(t).isValidSync('99999')).toBe(true)
  })
})
