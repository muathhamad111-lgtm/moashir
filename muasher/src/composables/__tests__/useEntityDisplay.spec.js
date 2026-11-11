import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { useEntityDisplay } from '@/composables/useEntityDisplay'
import { createTestGlobals } from '@/test-utils'

function rowsFor(entity) {
  let rows = []
  mount(
    defineComponent({
      setup() {
        rows = useEntityDisplay().rowsOf(entity, ['hasStrategicPlan'])
        return () => null
      },
    }),
    { global: createTestGlobals({ locale: 'ar' }) },
  )
  return rows
}

describe('useEntityDisplay — staff record', () => {
  it('shows an unanswered strategic plan as not provided instead of «لا» (P-6)', () => {
    expect(rowsFor({ hasStrategicPlan: null })[0].value).toBe('غير مدخل')
    expect(rowsFor({ hasStrategicPlan: false })[0].value).toBe('لا')
    expect(rowsFor({ hasStrategicPlan: true })[0].value).toBe('نعم')
  })
})
