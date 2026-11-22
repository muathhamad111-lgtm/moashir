import { describe, expect, it } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import { useForm } from 'vee-validate'
import SelfMainScoring from '@/components/assessment/self/SelfMainScoring.vue'
import { createTestGlobals } from '@/test-utils'

const DIRECT_WITH_INDICATORS = {
  id: 22,
  code: 'M1.2',
  name: { ar: 'الحوكمة', en: 'Governance' },
  evaluationType: 'direct',
  scoringType: 'achievement',
  points: [
    { id: 41, code: 'M1.2.1', description: { ar: 'سياسة', en: 'An approved policy' } },
    { id: 42, code: 'M1.2.2', description: { ar: 'مراجعة', en: 'A yearly review' } },
  ],
}

function mountMain(subs) {
  const Host = defineComponent({
    setup() {
      useForm({ initialValues: {} })
      return () =>
        h(SelfMainScoring, {
          main: { id: 11, name: { ar: 'القيادة', en: 'Leadership' }, subs },
          step: 1,
          total: 1,
          scores: { subs: {}, points: {} },
          typeOf: (node) => node.scoringType,
        })
    },
  })
  return mount(Host, { global: { plugins: createTestGlobals({ locale: 'en' }).plugins } })
}

describe('IndicatorGuidance in the self-assessment', () => {
  it('lists the indicators of a direct sub-criterion as guidance beside its one score', () => {
    const wrapper = mountMain([DIRECT_WITH_INDICATORS])
    const guidance = wrapper.find('.indicator-guidance')
    expect(guidance.text()).toContain('give the whole sub-criterion one score')
    expect(guidance.findAll('li').map((item) => item.text())).toEqual([
      'An approved policy',
      'A yearly review',
    ])
    expect(wrapper.findAll('input[name^="point-"]')).toHaveLength(0)
    expect(wrapper.find('input[name="sub-22"]').exists()).toBe(true)
  })

  it('shows no guidance block for a direct sub-criterion without indicators', () => {
    const wrapper = mountMain([{ ...DIRECT_WITH_INDICATORS, points: [] }])
    expect(wrapper.find('.indicator-guidance').exists()).toBe(false)
  })
})
