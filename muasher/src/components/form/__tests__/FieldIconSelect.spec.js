import { describe, expect, it } from 'vitest'
import { defineComponent, h, nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import { useForm } from 'vee-validate'
import { string as yupString, object as yupObject } from 'yup'
import FieldIconSelect from '@/components/form/FieldIconSelect.vue'
import { createTestGlobals } from '@/test-utils'

function factory({ icon = 'award', optional = false, schema, locale = 'ar' } = {}) {
  let form
  const Host = defineComponent({
    setup() {
      form = useForm({ initialValues: { icon }, validationSchema: schema })
      return () =>
        h(FieldIconSelect, {
          name: 'icon',
          label: 'الأيقونة',
          hint: 'اختر أيقونة من مجموعة أيقونات المنصة.',
          options: ['award', 'search', 'chart-line'],
          optional,
        })
    },
  })
  const wrapper = mount(Host, { global: createTestGlobals({ locale }) })
  return { wrapper, form: () => form }
}

describe('FieldIconSelect', () => {
  it('previews the chosen icon and lists every option with its translated name, key and glyph', async () => {
    const { wrapper } = factory()
    expect(wrapper.find('.icon-field__preview svg').attributes('data-icon')).toBe('award')
    expect(wrapper.find('input[role="combobox"]').element.value).toBe('جائزة')
    await wrapper.find('.dropdown-combobox').trigger('click')
    const options = wrapper.findAll('[role="option"]')
    expect(options.map((option) => option.find('.dropdown-option__label').text())).toEqual([
      'جائزة',
      'بحث',
      'مخطط خطي',
    ])
    expect(options.map((option) => option.find('.dropdown-option__description').text())).toEqual([
      'award',
      'search',
      'chart-line',
    ])
    expect(options.map((option) => option.find('svg').attributes('data-icon'))).toEqual([
      'award',
      'search',
      'chart-line',
    ])
    expect(wrapper.find('.field__hint').text()).toBe('اختر أيقونة من مجموعة أيقونات المنصة.')
  })

  it('shows English names in the English interface', async () => {
    const { wrapper } = factory({ locale: 'en' })
    await wrapper.find('.dropdown-combobox').trigger('click')
    expect(wrapper.findAll('.dropdown-option__label').map((label) => label.text())).toEqual([
      'Award',
      'Search',
      'Line chart',
    ])
  })

  it('offers a "no icon" choice for optional fields and clears the value with it', async () => {
    const { wrapper, form } = factory({ optional: true })
    await wrapper.find('.dropdown-combobox').trigger('click')
    const none = wrapper.findAll('[role="option"]')[0]
    expect(none.text()).toBe('بدون أيقونة')
    expect(none.find('.dropdown-option__icon').exists()).toBe(false)
    await none.trigger('click')
    expect(form().values.icon).toBe('')
    expect(wrapper.find('.icon-field__preview svg').exists()).toBe(false)
  })

  it('shows the validation message instead of the hint', async () => {
    const schema = yupObject({ icon: yupString().oneOf(['search'], 'اختر أيقونة من القائمة.') })
    const { wrapper, form } = factory({ schema })
    await form().validate()
    await nextTick()
    expect(wrapper.find('.field__error').text()).toBe('اختر أيقونة من القائمة.')
    expect(wrapper.find('.field__hint').exists()).toBe(false)
    expect(wrapper.find('input[role="combobox"]').attributes('aria-invalid')).toBe('true')
  })
})
