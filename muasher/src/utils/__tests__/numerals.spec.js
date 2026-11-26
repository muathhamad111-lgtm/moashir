import { describe, expect, it } from 'vitest'
import { toWesternDigits, westernizeDigits, westernizeInputDigits } from '@/utils/numerals'

describe('numerals', () => {
  it('turns Arabic-Indic and Persian digits into Western digits', () => {
    expect(westernizeDigits('٠١٢٣٤٥٦٧٨٩')).toBe('0123456789')
    expect(westernizeDigits('۰۱۲۳۴۵۶۷۸۹')).toBe('0123456789')
    expect(westernizeDigits('٨٧٫٥ ')).toBe('87.5 ')
    expect(toWesternDigits(' ٨٧٫٥ ')).toBe('87.5')
  })

  it('rewrites an input in place and keeps the caret where it was', () => {
    const input = document.createElement('input')
    document.body.append(input)
    input.value = '12٣٤'
    input.setSelectionRange(3, 3)

    expect(westernizeInputDigits(input)).toBe('1234')
    expect(input.value).toBe('1234')
    expect(input.selectionStart).toBe(3)
    input.remove()
  })
})
