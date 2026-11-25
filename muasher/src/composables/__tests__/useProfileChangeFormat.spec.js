import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { useProfileChangeFormat } from '@/composables/useProfileChangeFormat'
import { createTestGlobals } from '@/test-utils'

function labelsFor(field, values, locale = 'ar') {
  let labels = []
  mount(
    defineComponent({
      setup() {
        const { valueLabel } = useProfileChangeFormat()
        labels = values.map((value) => valueLabel(field, value))
        return () => null
      },
    }),
    { global: createTestGlobals({ locale }) },
  )
  return labels
}

describe('useProfileChangeFormat — governance answer', () => {
  it('reads the governance answer as yes, no or not set', () => {
    expect(labelsFor('has_governance_grade', [true, false, null])).toEqual([
      'نعم',
      'لا',
      'غير محدد',
    ])
    expect(labelsFor('has_governance_grade', [true, false, null], 'en')).toEqual([
      'Yes',
      'No',
      'Not set',
    ])
  })

  it('keeps «not provided» for the other empty values', () => {
    expect(labelsFor('governance_grade', [null, '86.5'])).toEqual(['غير مدخل', '86.50%'])
  })
})
