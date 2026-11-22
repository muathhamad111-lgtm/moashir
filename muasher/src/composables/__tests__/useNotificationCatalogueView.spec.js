import { describe, expect, it } from 'vitest'
import { ref } from 'vue'
import { useNotificationCatalogueView } from '@/composables/useNotificationCatalogueView'

const TYPES = [
  { code: 'CNT-01', module: 'CNT', audienceKinds: ['management'] },
  { code: 'REG-01', module: 'REG', audienceKinds: ['beneficiary', 'management'] },
  { code: 'XYZ-01', module: 'XYZ', audienceKinds: ['beneficiary'] },
]
const NAMES = { 'CNT-01': 'رسالة تواصل جديدة', 'REG-01': 'إنشاء حساب الجهة' }

function view() {
  return useNotificationCatalogueView(ref(TYPES), (code) => NAMES[code] ?? code)
}

describe('useNotificationCatalogueView', () => {
  it('groups by module in the platform order and keeps unknown modules last', () => {
    expect(view().groups.value.map((group) => group.module)).toEqual(['REG', 'CNT', 'XYZ'])
  })

  it('searches by name or code and filters by audience', () => {
    const { filters, visible } = view()
    filters.search = 'حساب'
    expect(visible.value.map((type) => type.code)).toEqual(['REG-01'])
    filters.search = 'cnt'
    expect(visible.value.map((type) => type.code)).toEqual(['CNT-01'])
    filters.search = ''
    filters.audience = 'beneficiary'
    expect(visible.value.map((type) => type.code)).toEqual(['REG-01', 'XYZ-01'])
  })
})
