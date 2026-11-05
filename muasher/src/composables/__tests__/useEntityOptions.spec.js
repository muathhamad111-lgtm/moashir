import { describe, expect, it, vi, beforeEach } from 'vitest'
import { defineComponent } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import ar from '@/i18n/locales/ar.json'
import en from '@/i18n/locales/en.json'
import { useEntityOptions } from '@/composables/useEntityOptions'
import { useRegions } from '@/composables/useRegions'
import { regionsService } from '@/services/regionsService'

vi.mock('@/services/regionsService', () => ({
  regionsService: { listRegions: vi.fn() },
}))

function harness(composable, locale = 'en') {
  let api
  const i18n = createI18n({ legacy: false, locale, fallbackLocale: 'en', messages: { ar, en } })
  const Harness = defineComponent({
    setup() {
      api = composable()
      return () => null
    },
  })
  mount(Harness, { global: { plugins: [i18n] } })
  return api
}

const REGIONS = [
  {
    id: 1,
    code: 'riyadh',
    name: { ar: 'منطقة الرياض', en: 'Riyadh Region' },
    cities: [
      { id: 1, code: 'riyadh', name: { ar: 'الرياض', en: 'Riyadh' } },
      { id: 2, code: 'diriyah', name: { ar: 'الدرعية', en: 'Diriyah' } },
    ],
  },
  {
    id: 2,
    code: 'makkah',
    name: { ar: 'منطقة مكة المكرمة', en: 'Makkah Region' },
    cities: [{ id: 12, code: 'jeddah', name: { ar: 'جدة', en: 'Jeddah' } }],
  },
]

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useEntityOptions', () => {
  it('offers exactly the three final entity types in both languages', () => {
    const english = harness(useEntityOptions)
    expect(english.entityTypeOptions.value).toEqual([
      { value: 'civil_association', label: 'Civil association' },
      { value: 'civil_foundation', label: 'Civil foundation' },
      { value: 'waqf', label: 'Waqf' },
    ])
    const arabic = harness(useEntityOptions, 'ar')
    expect(arabic.entityTypeOptions.value.map((option) => option.label)).toEqual([
      'جمعية أهلية',
      'مؤسسة أهلية',
      'وقف',
    ])
    expect(arabic.strategicPlanOptions.value.map((option) => option.value)).toEqual(['yes', 'no'])
  })
})

describe('useRegions', () => {
  it('loads the regions by id and lists the cities of the chosen region only', async () => {
    regionsService.listRegions.mockResolvedValue({ data: REGIONS })
    const api = harness(useRegions, 'ar')
    await api.load()
    await flushPromises()
    expect(api.status.value).toBe('success')
    expect(api.regionOptions.value).toEqual([
      { value: '1', label: 'منطقة الرياض' },
      { value: '2', label: 'منطقة مكة المكرمة' },
    ])
    expect(api.cityOptionsFor('2')).toEqual([{ value: '12', label: 'جدة' }])
    expect(api.cityOptionsFor('')).toEqual([])
  })

  it('reports the error state when the regions cannot be loaded', async () => {
    regionsService.listRegions.mockRejectedValue(new Error('network'))
    const api = harness(useRegions)
    await api.load()
    expect(api.status.value).toBe('error')
  })
})
