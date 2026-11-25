import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { useEntityDisplay } from '@/composables/useEntityDisplay'
import { createTestGlobals } from '@/test-utils'

function rowsFor(entity, fields = ['hasStrategicPlan']) {
  let rows = []
  mount(
    defineComponent({
      setup() {
        rows = useEntityDisplay().rowsOf(entity, fields)
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

  it('shows the governance grade after a «yes», «لا توجد» after a «no» and «غير محدد» when unanswered', () => {
    const governance = (entity) => rowsFor(entity, ['governanceGrade'])[0].value
    expect(governance({ hasGovernanceGrade: true, governanceGrade: '86.5' })).toBe('86.50%')
    expect(governance({ hasGovernanceGrade: false, governanceGrade: null })).toBe('لا توجد')
    expect(governance({ hasGovernanceGrade: null, governanceGrade: null })).toBe('غير محدد')
  })
})
