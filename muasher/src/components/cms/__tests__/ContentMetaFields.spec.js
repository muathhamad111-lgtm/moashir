import { describe, expect, it } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import { useForm } from 'vee-validate'
import { createTestGlobals } from '@/test-utils'
import ContentMetaFields from '../ContentMetaFields.vue'
import { contentFormValues } from '@/utils/cmsPayload'

function mountFields(sectionType, forItem = false, meta = {}) {
  const Host = defineComponent({
    setup() {
      useForm({ initialValues: contentFormValues({ meta }) })
      return () => h(ContentMetaFields, { sectionType, forItem })
    },
  })
  return mount(Host, { global: createTestGlobals() })
}

describe('ContentMetaFields', () => {
  it('shows bilingual inputs filled from the stored meta', () => {
    const wrapper = mountFields('about', false, {
      audienceLabel: { ar: 'تخدم المنصة', en: 'Serves' },
    })
    const inputs = wrapper.findAll('input')
    expect(inputs.length).toBe(8)
    expect(inputs.map((input) => input.element.value)).toContain('تخدم المنصة')
    expect(wrapper.text()).toContain('عنوان الفئات المخدومة')
  })

  it('offers the glyph list for items and the path tags as checkboxes', () => {
    const glyph = mountFields('about', true)
    expect(glyph.text()).toContain('الأيقونة المرسومة')
    const journey = mountFields('how_it_works', true)
    expect(journey.findAll('input[type="checkbox"]')).toHaveLength(3)
    expect(journey.text()).toContain('شاشة الخطوة')
  })

  it('shows a whole-number input for the sample score', () => {
    const wrapper = mountFields('levels', false, { sampleScore: 84 })
    const score = wrapper.find('input[type="number"]')
    expect(score.element.value).toBe('84')
  })

  it('renders nothing for types without landing fields', () => {
    expect(mountFields('faq').find('[data-cms-meta-fields]').exists()).toBe(false)
  })
})
